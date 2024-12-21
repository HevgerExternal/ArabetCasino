<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class FetchExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-exchange-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch exchange rates from CurrencyFreaks API and store in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKey = config('services.currencyfreaks.api_key');
        $endpoint = config('services.currencyfreaks.endpoint');

        $this->info('Fetching exchange rates from the CurrencyFreaks API...');

        $response = Http::get($endpoint, [
            'apikey' => $apiKey,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $apiDate = $data['date'] ?? now()->toDateTimeString(); // API-provided date
            $normalizedDate = Carbon::parse($apiDate)->format('Y-m-d'); // Normalize date to 'YYYY-MM-DD'

            $rates = $data['rates'] ?? [];

            if (isset($rates['LBP'])) {
                ExchangeRate::updateOrCreate(
                    ['currency' => 'LBP', 'date' => $normalizedDate],
                    ['rate' => $rates['LBP']]
                );

                $this->info("Exchange rate for LBP updated successfully: {$rates['LBP']}");
            } else {
                $this->error("LBP rate not found in the API response.");
            }
        } else {
            $this->error("Failed to fetch exchange rates: " . $response->body());
        }
    }

}
