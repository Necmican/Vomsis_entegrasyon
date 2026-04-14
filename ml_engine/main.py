import fastapi
import re
import warnings
import logging
from typing import Dict, List, Optional

import numpy as np
import pandas as pd
from pydantic import BaseModel
from prophet import Prophet

warnings.filterwarnings("ignore")
logging.getLogger("prophet").setLevel(logging.WARNING)
logging.getLogger("cmdstanpy").setLevel(logging.WARNING)

app = fastapi.FastAPI(title="Vomsis ML Engine")

# =============================================================================
# BÖLÜM 1: FİNANSAL TAHMİN (Facebook Prophet)
# =============================================================================

class ForecastRequest(BaseModel):
    dates: list[str]
    balances: list[float]          # Günlük kümülatif bakiye
    incomes: list[float] = []      # Günlük toplam gelir
    expenses: list[float] = []     # Günlük toplam gider
    special_days: list[dict] = []  # Özel gün flag'leri
    days_to_predict: int = 15


class BatchForecastRequest(BaseModel):
    accounts: Dict[str, ForecastRequest]







def predict_balance(dates, balances, incomes, expenses, special_days, days_to_predict):
    """
    3 aşamalı tahmin:
    1. Günlük geliri tahmin et (Prophet + mevsimsellik + özel günler)
    2. Günlük gideri tahmin et (Prophet + mevsimsellik + özel günler)
    3. Bakiye = son_gerçek_bakiye + kümülatif(tahmin_gelir - tahmin_gider)
    
    Backtesting: BAKİYE BAZLI doğruluk ölçer (gelir/gider MAPE değil).
    Gelir/gider verisi yoksa: doğrudan bakiye değişimini (delta) tahmin et.
    """
    n = len(dates)
    if n < 3:
        last = balances[-1] if balances else 0
        return {
            "forecast": [{"date": "", "predicted_balance": round(last, 2),
                          "upper_bound": round(last, 2), "lower_bound": round(last, 2)}] * days_to_predict,
            "accuracy": 0
        }
    
    # DataFrame oluştur
    df = pd.DataFrame({
        'ds': pd.to_datetime(dates),
        'balance': balances,
    })
    
    # Gelir/gider verisi var mı?
    has_income_expense = (
        len(incomes) == n and len(expenses) == n and
        (sum(incomes) > 0 or sum(expenses) > 0)
    )
    
    if has_income_expense:
        df['income'] = incomes
        df['expense'] = expenses
    
    # Özel gün flag'leri
    regressor_cols = []
    if special_days and len(special_days) == n:
        for key in ['is_month_end', 'is_month_start', 'is_weekend', 'is_quarter_end']:
            vals = [d.get(key, False) for d in special_days]
            if any(vals):  # En az bir True varsa anlamlı
                df[key] = [float(v) for v in vals]
                regressor_cols.append(key)
    
    # Son tarih
    last_date = df['ds'].iloc[-1]
    future_dates = pd.date_range(start=last_date + pd.Timedelta(days=1), periods=days_to_predict, freq='D')
    
    # Gelecek günler için regressor değerlerini hesapla
    future_regressors = {}
    for col in regressor_cols:
        vals = []
        for d in future_dates:
            if col == 'is_month_end':
                vals.append(float(d.day >= 25))
            elif col == 'is_month_start':
                vals.append(float(d.day <= 5))
            elif col == 'is_weekend':
                vals.append(float(d.weekday() >= 5))
            elif col == 'is_quarter_end':
                vals.append(float(d.month in [3, 6, 9, 12] and d.day >= 20))
            else:
                vals.append(0.0)
        future_regressors[col] = vals
    
    def _fit_and_predict(train_df, target_col, periods, extra_future_regs=None):
        """Bir hedef kolon için Prophet model kur ve tahmin döndür."""
        prophet_df = train_df[['ds', target_col]].rename(columns={target_col: 'y'}).copy()
        for col in regressor_cols:
            if col in train_df.columns:
                prophet_df[col] = train_df[col].astype(float)
        
        if len(prophet_df) < 14:
            return None, [0.0] * periods
        
        try:
            m = Prophet(
                weekly_seasonality=True,
                yearly_seasonality=len(prophet_df) > 60,
                daily_seasonality=False,
                interval_width=0.80,
                changepoint_prior_scale=0.05,
            )
            for col in regressor_cols:
                if col in prophet_df.columns:
                    m.add_regressor(col, mode='additive')
            m.fit(prophet_df)
            
            future = m.make_future_dataframe(periods=periods)
            if extra_future_regs:
                for col, vals in extra_future_regs.items():
                    existing = train_df[col].tolist() if col in train_df.columns else [0.0] * len(train_df)
                    future[col] = (existing + vals)[:len(future)]
            else:
                for col in regressor_cols:
                    if col in train_df.columns:
                        existing = train_df[col].tolist()
                        future[col] = existing[:len(future)]
            
            pred = m.predict(future)
            yhat = [max(0, round(v, 2)) for v in pred.iloc[-periods:]['yhat'].values]
            return m, yhat
        except Exception:
            return None, [0.0] * periods
    
    # =============================================
    # STRATEJI: Gelir/Gider varsa ayrı ayrı tahmin et
    # =============================================
    if has_income_expense:
        # --- BACKTESTING: Bakiye bazlı doğruluk hesabı ---
        # Son 15 günü ayır, modellerle bakiye tahmini yap, gerçekle kıyasla
        bt_size = min(15, max(7, n // 7))
        accuracy = 0.0
        
        if n > bt_size + 30:
            try:
                train_bt = df.iloc[:-bt_size].copy()
                test_bt = df.iloc[-bt_size:].copy()
                
                # Test dönemi için regressor'ları hazırla
                test_regs = {}
                for col in regressor_cols:
                    if col in test_bt.columns:
                        test_regs[col] = test_bt[col].tolist()
                
                _, bt_inc = _fit_and_predict(train_bt, 'income', bt_size, test_regs)
                _, bt_exp = _fit_and_predict(train_bt, 'expense', bt_size, test_regs)
                
                # Tahmin edilen bakiyeleri hesapla
                bt_start_balance = train_bt['balance'].iloc[-1]
                bt_pred_balances = []
                cum = 0.0
                for i in range(bt_size):
                    cum += bt_inc[i] - bt_exp[i]
                    bt_pred_balances.append(bt_start_balance + cum)
                
                # Gerçek bakiyelerle MAPE
                actual_bals = test_bt['balance'].values
                pred_bals = np.array(bt_pred_balances)
                mask = actual_bals != 0
                if mask.any():
                    mape = np.mean(np.abs((actual_bals[mask] - pred_bals[mask]) / actual_bals[mask])) * 100
                    accuracy = max(0, min(100, 100 - mape))
            except Exception:
                accuracy = 0
        
        # --- ASIL TAHMİN: Tüm veriyle model kur ---
        future_regs_for_pred = {col: future_regressors[col] for col in regressor_cols}
        
        model_inc, pred_incomes = _fit_and_predict(df, 'income', days_to_predict, future_regs_for_pred)
        model_exp, pred_expenses = _fit_and_predict(df, 'expense', days_to_predict, future_regs_for_pred)
        
        # --- BAKİYE = son_bakiye + kümülatif(gelir - gider) ---
        last_balance = balances[-1]
        forecast_results = []
        cumulative = 0.0
        
        # Güven aralığı genişliği: bakiye büyüklüğünün %1-3'ü kadar
        base_uncertainty = abs(last_balance) * 0.005
        
        for i in range(days_to_predict):
            cumulative += pred_incomes[i] - pred_expenses[i]
            pred_bal = last_balance + cumulative
            # Güven bandı: zaman ilerledikçe belirsizlik artar
            uncertainty = base_uncertainty * (i + 1) + (pred_incomes[i] + pred_expenses[i]) * 0.15
            forecast_results.append({
                "date": future_dates[i].strftime("%Y-%m-%d"),
                "predicted_balance": round(pred_bal, 2),
                "predicted_income": pred_incomes[i],
                "predicted_expense": pred_expenses[i],
                "upper_bound": round(pred_bal + uncertainty, 2),
                "lower_bound": round(pred_bal - uncertainty, 2),
            })
        
        return {"forecast": forecast_results, "accuracy": round(accuracy, 1)}
    
    # =============================================
    # FALLBACK: Gelir/gider yoksa doğrudan bakiye delta tahmin et
    # =============================================
    df['delta'] = df['balance'].diff().fillna(0)
    
    # Delta backtesting
    bt_size = min(15, max(7, n // 7))
    accuracy = 0.0
    if n > bt_size + 14:
        try:
            train_bt = df.iloc[:-bt_size].copy()
            test_bt = df.iloc[-bt_size:].copy()
            test_regs = {col: test_bt[col].tolist() for col in regressor_cols if col in test_bt.columns}
            _, bt_deltas = _fit_and_predict(train_bt, 'delta', bt_size, test_regs)
            
            bt_start = train_bt['balance'].iloc[-1]
            cum = 0.0
            bt_pred = []
            for d in bt_deltas:
                cum += d
                bt_pred.append(bt_start + cum)
            
            actual_bals = test_bt['balance'].values
            pred_bals = np.array(bt_pred)
            mask = actual_bals != 0
            if mask.any():
                mape = np.mean(np.abs((actual_bals[mask] - pred_bals[mask]) / actual_bals[mask])) * 100
                accuracy = max(0, min(100, 100 - mape))
        except Exception:
            accuracy = 0
    
    # Asıl delta tahmin
    future_regs_for_pred = {col: future_regressors[col] for col in regressor_cols}
    model_delta, pred_deltas = _fit_and_predict(df, 'delta', days_to_predict, future_regs_for_pred)
    
    last_balance = balances[-1]
    forecast_results = []
    cumulative = 0.0
    base_uncertainty = abs(last_balance) * 0.005
    
    for i in range(days_to_predict):
        cumulative += pred_deltas[i]
        pred_bal = last_balance + cumulative
        uncertainty = base_uncertainty * (i + 1)
        forecast_results.append({
            "date": future_dates[i].strftime("%Y-%m-%d"),
            "predicted_balance": round(pred_bal, 2),
            "upper_bound": round(pred_bal + uncertainty, 2),
            "lower_bound": round(pred_bal - uncertainty, 2),
        })
    
    return {"forecast": forecast_results, "accuracy": round(accuracy, 1)}


@app.post("/api/forecast_batch")
def generate_forecast_batch(request: BatchForecastRequest):
    """Birden fazla para birimi için toplu tahmin üret."""
    try:
        forecasts = {}
        accuracies = {}
        for currency, req in request.accounts.items():
            if len(req.balances) > 0:
                res = predict_balance(
                    req.dates,
                    req.balances,
                    req.incomes,
                    req.expenses,
                    req.special_days,
                    req.days_to_predict
                )
                forecasts[currency] = res['forecast']
                accuracies[currency] = res['accuracy']
            else:
                forecasts[currency] = []
                accuracies[currency] = 0
        return {"status": "success", "forecasts": forecasts, "accuracies": accuracies}
    except Exception as e:
        raise fastapi.HTTPException(status_code=500, detail=str(e))


@app.post("/api/forecast")
def generate_forecast(request: ForecastRequest):
    """Tek para birimi için tahmin üret."""
    try:
        results = predict_balance(
            request.dates,
            request.balances,
            request.incomes,
            request.expenses,
            request.special_days,
            request.days_to_predict
        )
        return {"status": "success", **results}
    except Exception as e:
        raise fastapi.HTTPException(status_code=500, detail=str(e))


# =============================================================================
# BÖLÜM 2: NLP KÜMELEME — TF-IDF + KMeans (Gerçek Makine Öğrenmesi)
# =============================================================================
# STOP-WORD LİSTESİ — Kapsamlı, Hiyerarşik, Ticari Odaklı
# Mantık: Bu listede olmayanlar = işlemsel değer taşıyan kelimeler
#          (Firma adları, vergi türleri, ürün/hizmet adları korunur)
# =============================================================================

# ── Türk Bankaları (banka adları hiçbir zaman anlamlı anahtar kelime olmaz) ──
TURKISH_BANK_NAMES = {
    "akbank", "garanti", "garantibbva", "isbank", "isbankasi", "ziraat",
    "ziraatbankasi", "halkbank", "vakifbank", "vakiflar", "ykb", "yapi",
    "yapikredi", "ing", "ingbank", "hsbc", "qnb", "qnbfinansbank", "finansbank",
    "teb", "turk", "ekonomi", "denizbank", "sekerbank", "anadolubank", "fibabanka",
    "burgan", "burganbank", "alternatifbank", "aktif", "aktifbank", "odea",
    "odeabank", "nurol", "nurolbank", "merkezbank", "merkez", "takasbank",
    "kuveytturk", "albaraka", "ziraat_katilim", "kuveyt", "finans",
    # Uluslararası
    "deutsche", "bnp", "paribas", "citibank", "citi", "jpmorgan", "rabobank",
}

# ── İşlemsel Fiiller & Kanallar (ne yapıldığı değil, KİMDEN/KİME önemli) ──
TRANSACTION_VERBS = {
    # Para hareketleri
    "havale", "havalesi", "eft", "trf", "vrm", "virman", "transfer", "transferi",
    "cekim", "cekimi", "para", "yatirma", "yatirim", "odeme", "odemesi",
    "tahsilat", "tahsilati", "kesinti", "kesintisi", "iade", "iadesin",
    "geri", "gonderim", "gonderimi",
    # Banka operasyonları (hiçbir anlam ifade etmez)
    "bloke", "blokesi", "blokaj", "kaldirma", "kaldirimi",
    "indirim", "provizyon", "komisyon", "faiz", "faizi",
    "kartsiz", "nakit", "nakdi",
    # Yön/akış
    "gelen", "giden", "alinan", "verilen", "yapilan", "gerceklesen",
    "hesaba", "hesaptan", "uzerinden", "tarafindan", "araciligiyla",
}

# ── Banka/Finans Altyapı Terimleri ──
BANKING_INFRA = {
    "banka", "bankasi", "bank", "hesap", "hesabi", "hesapno",
    "iban", "swift", "bic", "pos", "atm", "sube", "subesi", "kart", "karti",
    "kredi", "kredisi", "debit", "mevduat", "vadesiz", "vadeli",
}

# ── Referans / Dolgu Kelimeleri ──
REFERENCE_WORDS = {
    "tarih", "tarihi", "tarihli", "not", "ref", "referans",
    "kod", "kodu", "aciklama", "bilgi", "bilgisi", "no", "nolu", "numarali",
    "dekont", "sira", "fis", "fisi", "belge", "belgesi", "islem",
    "sn", "sayin", "no", "numarasi", "numarali",
}

# ── Şirket Hukuki Ekleri (firma ismini korur ama eki atar) ──
COMPANY_SUFFIXES = {
    "ltd", "sti", "sirketi", "limited", "sirket",
    "anonim", "koll", "ort", "tic", "san", "as", "asa", "ins",
}

# ── Genel Türkçe Bağlaçlar / Edatlar ──
TR_STOPWORDS = {
    "ve", "ile", "bir", "bu", "su", "da", "de", "den", "icin", "olan",
    "olarak", "uzere", "gibi", "kadar", "karsi", "gore", "hem", "ya",
    "ama", "veya", "ancak", "fakat", "the", "and", "for", "of", "in",
}

# ── Birleşik tam stop-word seti ──
TURKISH_FINANCE_STOP_WORDS = (
    TURKISH_BANK_NAMES |
    TRANSACTION_VERBS  |
    BANKING_INFRA      |
    REFERENCE_WORDS    |
    COMPANY_SUFFIXES   |
    TR_STOPWORDS
)


def normalize_turkish(text: str) -> str:
    """Türkçe karakterleri ASCII'ye dönüştür ve küçük harfe çevir."""
    tr_map = str.maketrans(
        "şıöüğçŞİÖÜĞÇ",
        "siougcSIOUGC"
    )
    return text.translate(tr_map).lower()


def clean_description(text: str, extra_stops: list = []) -> str:
    """
    Banka işlem açıklamasını NLP için hazırla.
    
    Adım adım temizleme hiyerarşisi:
    1. Türkçe normalizasyon (ş→s, ı→i vb.)
    2. IBAN & büyük sayı silme (TR64... , 1234567890)
    3. Tarih formatları silme (01.01.2024, 2024-01-01)
    4. Banka referans kodu silme (PROV4532, XVR12345 - 6+ karışık karakter)
    5. Noktalama & özel karakter silme
    6. Stop-word filtresi (banka adı, fiil, ek, bağlaç)
    7. 1-2 harfli tokeni at
    8. Tekrar eden anlamlı aynı kök kelimeyi sadeleştir
    """
    if not text or not text.strip():
        return ""

    normalized = normalize_turkish(text)

    # 1) IBAN pattern: TR/DE/GB + rakamlar
    cleaned = re.sub(r'\b[a-z]{2}\d{2}[a-z0-9]{10,30}\b', ' ', normalized)

    # 2) 5 ve daha fazla hane: hesap no, ref no, TC kimlik, tutar kodu
    cleaned = re.sub(r'\b\d{5,}\b', ' ', cleaned)

    # 3) Tarih formatları: DD.MM.YYYY / YYYY-MM-DD / DD/MM/YY
    cleaned = re.sub(r'\b\d{1,4}[./-]\d{1,2}[./-]\d{1,4}\b', ' ', cleaned)

    # 4) Saat formatı: 10:35:22
    cleaned = re.sub(r'\b\d{1,2}:\d{2}(:\d{2})?\b', ' ', cleaned)

    # 5) Karışık alfasayısal referans kodu (6+ karakter, hem harf hem rakam içeriyor)
    #    Ancak "a101", "m1" gibi KISA firma isimlerini korumak için 6+ şart
    cleaned = re.sub(r'\b(?=[a-z0-9]*[a-z])(?=[a-z0-9]*[0-9])[a-z0-9]{6,}\b', ' ', cleaned)

    # 6) Noktalama & tire & parantez → boşluk
    cleaned = re.sub(r'[^a-z0-9 ]', ' ', cleaned)

    # 7) Tekrarlı boşluk
    cleaned = re.sub(r'\s+', ' ', cleaned).strip()

    # 8) Stop-word filtresi (sistem + kullanıcı tanımlı)
    all_stops = TURKISH_FINANCE_STOP_WORDS.copy()
    for s in extra_stops:
        all_stops.add(normalize_turkish(s))

    words = [w for w in cleaned.split() if w not in all_stops and len(w) > 2]

    # 9) Bigram deduplikasyonu: "para cekim cekim" → "cekim" zaten stop; 
    #    aynı kelime arka arkaya tekrarlanmışsa bir kez tut
    deduped = []
    prev = None
    for w in words:
        if w != prev:
            deduped.append(w)
        prev = w

    return " ".join(deduped)


class ClusterRequest(BaseModel):
    descriptions: List[str]
    transaction_ids: List[int] = []   # Opsiyonel: hangi ID'ler olduğunu da bil
    n_clusters: int = 20              # Kaç kümeye ayıralım
    exclusions: List[str] = []        # HARİÇ tutulacak kelimeler (kullanıcı tanımlı)


@app.post("/api/cluster_transactions")
def cluster_transactions(request: ClusterRequest):
    """
    TF-IDF + KMeans ile işlem açıklamalarını kümele.
    Her küme için: temsil cümlesi, anahtar kelimeler, kaç işlem içerdiği,
    örnek açıklamalar ve ilgili transaction ID'ler döner.
    """
    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
        from sklearn.cluster import KMeans
        from sklearn.metrics.pairwise import cosine_similarity

        descriptions   = request.descriptions
        txn_ids        = request.transaction_ids if request.transaction_ids else list(range(len(descriptions)))
        n_clusters_req = request.n_clusters

        # Çok az açıklama varsa kümeleme yapamazsın
        if len(descriptions) < 5:
            return {"status": "success", "clusters": []}

        # Temizlenmiş açıklamalar
        cleaned_descs = [clean_description(d, request.exclusions) for d in descriptions]

        # Boş açıklamaları ele al
        valid_mask = [i for i, d in enumerate(cleaned_descs) if d.strip()]
        if len(valid_mask) < 5:
            return {"status": "success", "clusters": []}

        valid_cleaned  = [cleaned_descs[i] for i in valid_mask]
        valid_original = [descriptions[i]  for i in valid_mask]
        valid_ids      = [txn_ids[i]        for i in valid_mask]

        # Gerçek küme sayısı: veri sayısını geçemez
        n_clusters = min(n_clusters_req, len(valid_cleaned) // 3, 30)
        n_clusters = max(n_clusters, 2)

        # ─── TF-IDF Vektorizasyonu ───────────────────────────────────────────
        # ngram_range=(1,2) → hem tekli hem de ikili kelimeyi (bigram) yakalar
        vectorizer = TfidfVectorizer(
            ngram_range=(1, 2),
            min_df=2,               # En az 2 belgede geçmeli
            max_df=0.85,            # Belgelerin %85'inden fazlasında geçenler genel → sil
            sublinear_tf=True,      # log(1+tf) → aşırı sık kelimeleri bask
            max_features=3000
        )
        tfidf_matrix = vectorizer.fit_transform(valid_cleaned)
        feature_names = vectorizer.get_feature_names_out()

        # ─── KMeans Kümeleme ─────────────────────────────────────────────────
        kmeans = KMeans(n_clusters=n_clusters, random_state=42, n_init=10, max_iter=300)
        labels = kmeans.fit_predict(tfidf_matrix)

        # ─── Her Küme için Sonuç Oluştur ─────────────────────────────────────
        clusters = []
        for cluster_id in range(n_clusters):
            # Bu kümedeki indexleri bul
            cluster_indices = [i for i, lbl in enumerate(labels) if lbl == cluster_id]
            if not cluster_indices:
                continue

            cluster_descs   = [valid_original[i] for i in cluster_indices]
            cluster_cleaned = [valid_cleaned[i]   for i in cluster_indices]
            cluster_ids     = [valid_ids[i]        for i in cluster_indices]

            # Küme merkezine en yakın açıklamayı "temsil" olarak seç
            cluster_vectors  = tfidf_matrix[cluster_indices]
            center_vector    = kmeans.cluster_centers_[cluster_id]
            similarities     = cosine_similarity(cluster_vectors, center_vector.reshape(1, -1)).flatten()
            rep_idx          = int(similarities.argmax())
            representative   = cluster_descs[rep_idx]

            # Kümenin anahtar kelimelerini bul (merkezdeki en yüksek TF-IDF skorlular)
            center_weights = kmeans.cluster_centers_[cluster_id]
            top_kw_indices = center_weights.argsort()[-6:][::-1]
            keywords = [feature_names[j] for j in top_kw_indices if center_weights[j] > 0.01]

            # Örnek açıklamalar (maksimum 3, en yakın olanlar)
            sorted_by_sim = sorted(zip(similarities, cluster_descs), reverse=True)
            samples = [desc for _, desc in sorted_by_sim[:3]]

            clusters.append({
                "cluster_id":     cluster_id,
                "representative": representative,
                "keywords":       keywords,
                "count":          len(cluster_indices),
                "transaction_ids": cluster_ids,
                "sample_descriptions": samples,
            })

        # Küme büyüklüğüne göre sırala (en büyük küme en üste)
        clusters.sort(key=lambda c: c["count"], reverse=True)

        return {"status": "success", "clusters": clusters}

    except Exception as e:
        raise fastapi.HTTPException(status_code=500, detail=f"Kümeleme hatası: {str(e)}")


@app.get("/health")
def health_check():
    return {"status": "ok", "engine": "Vomsis ML Engine v3 — Prophet"}