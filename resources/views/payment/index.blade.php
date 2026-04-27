<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Güvenli Ödeme Ekranı | Vomsis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .payment-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-control, .form-select { border-radius: 8px; padding: 12px 15px; border: 1px solid #ddd; }
        .form-control:focus, .form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1); }
        .btn-pay { background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%); border: none; padding: 15px; border-radius: 8px; font-weight: bold; font-size: 1.1rem; letter-spacing: 1px;}
        .btn-pay:hover { opacity: 0.9; transform: translateY(-1px); }
        .secure-badge { color: #198754; font-size: 0.85rem; font-weight: bold; }
        
        /* Animasyonlar için */
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            
            @if(session('mesaj'))
                <div class="alert alert-success shadow-sm rounded-3">
                    <i class="fas fa-check-circle me-2"></i> {{ session('mesaj') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger shadow-sm rounded-3">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card payment-card mt-3">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold mb-1">Güvenli Ödeme</h4>
                        <p class="text-muted small">Altyapı: {{ $activePos->name ?? 'Vomsis POS' }}</p>
                    </div>

                    <form action="{{ route('payment.process') }}" method="POST">
                        @csrf
                       <input type="hidden" name="virtual_pos_id" value="{{ $activePos->id ?? '' }}">

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">Ödenecek Tutar (TL)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-success fw-bold">₺</span>
                                <input type="number" name="amount" class="form-control border-start-0 ps-0" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">Kart Üzerindeki İsim</label>
                            <input type="text" name="card_name" class="form-control" placeholder="AD SOYAD" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">Kart Numarası</label>
                            <div class="input-group">
                                <input type="text" name="card_number" id="cardNumber" class="form-control border-end-0" placeholder="**** **** **** ****" maxlength="16" required>
                                <span class="input-group-text bg-white"><i class="far fa-credit-card text-muted"></i></span>
                            </div>
                            
                            <div id="cardInfoBox" class="mt-2 p-2 bg-light border rounded d-none align-items-center fade-in">
                                <i class="fas fa-university text-primary me-2"></i>
                                <span id="bankNameText" class="fw-bold text-dark" style="font-size: 0.85rem;"></span>
                                <span id="cardFamilyText" class="badge bg-secondary ms-auto"></span>
                            </div>
                        </div>

                        <div class="mb-4 d-none fade-in" id="installmentsWrapper">
                            <label class="form-label text-muted fw-bold small text-primary"><i class="fas fa-hand-holding-usd me-1"></i> Taksit Seçenekleri</label>
                            <select name="installment" id="installmentSelect" class="form-select border-primary fw-bold" style="background-color: #f8fbff; color: #0043a8;">
                                </select>
                            <input type="hidden" name="installment_ratio" id="installmentRatio" value="">
                        </div>

                        <div class="row mb-4">
                            <div class="col-7">
                                <label class="form-label text-muted fw-bold small">Son Kullanma Tarihi</label>
                                <div class="d-flex gap-2">
                                    <select name="expire_month" class="form-select" required>
                                        <option value="">Ay</option>
                                        @for($i=1; $i<=12; $i++)
                                            <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</option>
                                        @endfor
                                    </select>
                                    <select name="expire_year" class="form-select" required>
                                        <option value="">Yıl</option>
                                        @for($i=date('Y'); $i<=date('Y')+10; $i++)
                                            <option value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <div class="col-5">
                                <label class="form-label text-muted fw-bold small">CVV</label>
                                <input type="text" name="cvv" class="form-control" placeholder="***" maxlength="4" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-pay text-white">
                            <i class="fas fa-lock me-2"></i> ÖDEMEYİ TAMAMLA
                        </button>

                        <div class="text-center mt-3 secure-badge">
                            <i class="fas fa-shield-alt"></i> 256-bit SSL ile Korunmaktadır
                        </div>
                    </form>

                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardNumberInput = document.getElementById('cardNumber');
    const cardInfoBox = document.getElementById('cardInfoBox');
    const bankNameText = document.getElementById('bankNameText');
    const cardFamilyText = document.getElementById('cardFamilyText');
    const installmentsWrapper = document.getElementById('installmentsWrapper');
    const installmentSelect = document.getElementById('installmentSelect');
    const installmentRatioInput = document.getElementById('installmentRatio');
    
    // Laravel CSRF Token
    const csrfToken = '{{ csrf_token() }}';

    // Klavye dinleyici
    cardNumberInput.addEventListener('input', function(e) {
        // Sadece rakamları al
        let rawNumber = e.target.value.replace(/\D/g, '');
        
        // Kart 6 haneye ulaştıysa Vomsis API'sine sor
        if (rawNumber.length === 6) {
            fetchBinData(rawNumber);
        } 
        // 6 haneden aza düştüyse kutuları geri gizle
        else if (rawNumber.length < 6) {
            cardInfoBox.classList.add('d-none');
            installmentsWrapper.classList.add('d-none');
            installmentSelect.innerHTML = '';
            installmentRatioInput.value = '';
        }
    });

    function fetchBinData(binNumber) {
        // Yükleniyor durumu
        bankNameText.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sorgulanıyor...';
        cardFamilyText.innerHTML = '';
        cardInfoBox.classList.remove('d-none');
        installmentsWrapper.classList.add('d-none');

        // Arka plana istek at
        fetch('{{ route('payment.bincheck', [], false) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ cc_number: binNumber })
        })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                const info = res.data.card_info;
                const installments = res.data.installments;

                // 1. Banka Adı ve Kart Ailesini (Bonus, Axess vs) yaz
                bankNameText.innerText = info.bank_name;
                cardFamilyText.innerText = (info.card_family_name || 'Bilinmiyor') + ' / ' + (info.card_association || '').toUpperCase();

                // 2. Taksitleri Çiz
                installmentSelect.innerHTML = ''; 
                
                installments.forEach(inst => {
                    let option = document.createElement('option');
                    option.value = inst.installment;
                    option.dataset.ratio = inst.ratio ?? '';
                    
                    // Vade farkı varsa göster
                    let vadeFarki = inst.ratio !== "0" ? ` (+%${inst.ratio} Fark)` : '';
                    
                    // Örnek Metin: 3 Taksit - 105.00 TRY (+%5 Fark)
                    option.text = `${inst.title} - ${inst.amount} ${inst.currency}${vadeFarki}`;
                    installmentSelect.appendChild(option);
                });

                // Taksit kutusunu aç
                installmentsWrapper.classList.remove('d-none');

                const selected = installmentSelect.options[installmentSelect.selectedIndex];
                installmentRatioInput.value = selected?.dataset?.ratio ?? '';
            } else {
                // Artık Vomsis'in gönderdiği mesajı ekrana basıyoruz!
                bankNameText.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> ' + (res.message || 'Banka tespit edilemedi.') + '</span>';
            }
        })
        .catch(error => {
            bankNameText.innerHTML = '<span class="text-danger"><i class="fas fa-wifi"></i> Bağlantı hatası.</span>';
            console.error('BIN Check Error:', error);
        });
    }

    installmentSelect.addEventListener('change', function () {
        const selected = installmentSelect.options[installmentSelect.selectedIndex];
        installmentRatioInput.value = selected?.dataset?.ratio ?? '';
    });
});
</script>

</body>
</html>