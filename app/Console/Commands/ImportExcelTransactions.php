<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Bank;
use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ImportExcelTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'excel:import-transactions {file=storage/app/hesap_hareketleri.csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import transactions from an exported Vomsis CSV/Excel file, marking them as is_real=1';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("Dosya bulunamadı: {$filePath}");
            return;
        }

        $this->info("İçe aktarım başlatılıyor: {$filePath}");

        $handle = fopen($filePath, "r");
        
        // CSV başlıklarını oku
        $headerLine = fgets($handle);
        // UTF-8 BOM Temizle
        $headerLine = str_replace("\xEF\xBB\xBF", '', $headerLine); 
        $headers = str_getcsv($headerLine, ",");
        
        $count = 0;
        $inserted = 0;

        $this->output->progressStart(1000); 

        // Önceki gerçek verileri temizle (opsiyonel ama plan gereği temizliyoruz)
        Transaction::withoutGlobalScopes()->where('is_real', 1)->delete();
        $this->info(" \nEski hatalı atanan gerçek veriler silindi, tertemiz başlanıyor...");

        // Veritabanı sorgularının önbelleklenmesi (cache banks/accounts)
        $cachedBanks = [];
        $cachedAccounts = [];

        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            if(count($headers) !== count($data)) {
                continue;
            }
            
            $row = array_combine($headers, $data);
            $count++;

            $vomsisId = $row['id'] ?? null;
            if (!$vomsisId) continue;
            
            // 1) Bankayı Bul veya Oluştur
            $bankNameRaw = !empty($row['bank_title']) ? $row['bank_title'] : (!empty($row['bank_name']) ? $row['bank_name'] : 'Genel Banka');
            
            // Önbellekte varsa kullan, yoksa DB'den bul/yarat
            if (!isset($cachedBanks[$bankNameRaw])) {
                // Banka ismiyle aynı kaydı bul, yoksa vomsis_bank_id boş kalmasın diye eklendi
                $bank = Bank::firstOrCreate(
                    ['bank_name' => $bankNameRaw],
                    ['vomsis_bank_id' => 'EXCEL_' . Str::upper(Str::slug($bankNameRaw)) . '_' . rand(1000, 9999)]
                );
                $cachedBanks[$bankNameRaw] = $bank->id;
            }
            $bankId = $cachedBanks[$bankNameRaw];

            // 2) Banka Hesabını Bul veya Oluştur
            $accountNumber = !empty($row['account_number']) ? $row['account_number'] : 'HESAP-'.rand(100,999);
            $cacheKey = $bankId . '_' . $accountNumber;
            
            $currency = !empty($row['fec_name']) ? strtoupper($row['fec_name']) : 'TL';
            if ($currency === 'TRY') $currency = 'TL';

            if (!isset($cachedAccounts[$cacheKey])) {
                $accountName = $bankNameRaw . ' - ' . $accountNumber;
                $account = BankAccount::firstOrCreate(
                    [
                        'bank_id' => $bankId,
                        'account_name' => $accountName
                    ],
                    [
                        'vomsis_account_id' => $accountNumber,
                        'iban' => $row['reciever_iban'] ?? null,
                        'currency' => $currency,
                        'balance' => $row['current_balance'] ?? 0
                    ]
                );
                $cachedAccounts[$cacheKey] = $account;
            }
            $accountModel = $cachedAccounts[$cacheKey];
            
            // Bakiyeyi güncele (Son işlemin bakiyesi hesaba kalır)
            if (isset($row['current_balance'])) {
                $accountModel->balance = $row['current_balance'];
                // Her seferinde save atıp performansı öldürmemek için, isterseniz en son topluca kaydedilebilir 
                // ama bellek için direct update atabiliriz
                BankAccount::where('id', $accountModel->id)->update(['balance' => $row['current_balance']]);
            }

            // Tarih kontrolü
            $dateStr = $row['transaction_date'] ?? null;
            $transactionDate = $dateStr ? Carbon::parse($dateStr) : now();

            // Miktar (Eğer credit ise pozitif, debit ise negatif)
            $amount = $row['amount'] ?? 0;
            if(isset($row['debitCredit']) && strtolower($row['debitCredit']) === 'debit') {
                $amount = -abs($amount);
            } else if (isset($row['debitCredit']) && strtolower($row['debitCredit']) === 'credit') {
                $amount = abs($amount);
            }
            
            // İşlem Kaydı
            Transaction::updateOrCreate(
                ['vomsis_transaction_id' => $vomsisId],
                [
                    'bank_account_id' => $accountModel->id,
                    'description' => $row['description'] ?? 'Aktarılmış işlem',
                    'amount' => $amount,
                    'type' => $row['transaction_type'] ?? null,
                    'balance' => $row['current_balance'] ?? 0,
                    'transaction_date' => $transactionDate,
                    'transaction_type_code' => $row['transaction_type'] ?? null,
                    'is_real' => 1,
                ]
            );

            $inserted++;
            if($inserted % 100 == 0) {
                $this->output->progressAdvance(100);
            }
        }
        $this->output->progressFinish();
        fclose($handle);

        $this->info("İşlem tamamlandı! Toplam okunan: $count - Başarıyla eklenen/güncellenen: $inserted");
    }
}
