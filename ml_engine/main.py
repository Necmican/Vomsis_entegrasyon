import fastapi
from pydantic import BaseModel
import pandas as pd
from statsmodels.tsa.holtwinters import ExponentialSmoothing
import warnings
from typing import Dict

# İstatistiksel uyarıları gizle
warnings.filterwarnings("ignore")

app = fastapi.FastAPI(title="Vomsis ML Engine")

# Laravel'den gelecek JSON verisinin şablonu
class ForecastRequest(BaseModel):
    dates: list[str]
    balances: list[float]
    days_to_predict: int = 15

# Çoklu hesaplar (kurlar) için toplu istek şablonu
class BatchForecastRequest(BaseModel):
    accounts: Dict[str, ForecastRequest]

def predict_series(dates: list[str], balances: list[float], days_to_predict: int):
    if len(balances) < 3:
        # Yeterli veri yoksa son bakiyeyi düz çizgi olarak uzat
        last_val = balances[-1] if balances else 0.0
        if not dates:
            return []
        future_dates = pd.date_range(start=pd.to_datetime(dates[-1]) + pd.Timedelta(days=1), periods=days_to_predict)
        return [{"date": d.strftime("%Y-%m-%d"), "predicted_balance": round(last_val, 2)} for d in future_dates]

    df = pd.DataFrame({
        'date': pd.to_datetime(dates),
        'balance': balances
    })
    df.set_index('date', inplace=True)
    
    # Holt-Winters Exponential Smoothing (ARIMA'ya kıyasla daha iyi trend takibi sağlar)
    try:
        # add: pozitif veya negatif trendi katar
        model = ExponentialSmoothing(df['balance'], trend='add', initialization_method="estimated")
        fitted_model = model.fit()
        forecast = fitted_model.forecast(steps=days_to_predict)
    except Exception as e:
        # Matematiksel bir hata olursa (örn. veriler sabitse), son değeri kullan
        last_val = df['balance'].iloc[-1]
        forecast = [last_val] * days_to_predict
        
    future_dates = pd.date_range(start=df.index[-1] + pd.Timedelta(days=1), periods=days_to_predict)
    
    results = []
    for date, pred_balance in zip(future_dates, forecast):
        results.append({
            "date": date.strftime("%Y-%m-%d"),
            "predicted_balance": round(pred_balance, 2)
        })
    return results

@app.post("/api/forecast_batch")
def generate_forecast_batch(request: BatchForecastRequest):
    try:
        response = {}
        for currency, req in request.accounts.items():
            if len(req.balances) > 0:
                response[currency] = predict_series(req.dates, req.balances, req.days_to_predict)
            else:
                response[currency] = []
        return {"status": "success", "forecasts": response}
    except Exception as e:
        raise fastapi.HTTPException(status_code=500, detail=str(e))

@app.post("/api/forecast")
def generate_forecast(request: ForecastRequest):
    try:
        results = predict_series(request.dates, request.balances, request.days_to_predict)
        return {"status": "success", "forecast": results}
    except Exception as e:
        raise fastapi.HTTPException(status_code=500, detail=str(e))