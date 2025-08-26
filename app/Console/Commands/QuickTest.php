<?php
// app/Console/Commands/QuickTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class QuickTest extends Command
{
    protected $signature = 'quick:test';
    protected $description = 'Quick API test';

    public function handle()
    {
        $nik = '24060185';
        $password = 'eci2017';
        $url = 'http://10.101.0.85/newhris_api/api/login2.php';

        $this->info("Testing API login (mengikuti JS yang berhasil)...");

        // Test 1: Replicate exact JS fetch behavior
        $this->info("\n=== Test 1: Replicate JS Fetch ===");
        try {
            $payload = [
                'username' => $nik,
                'password' => $password,
            ];

            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withBody(json_encode($payload), 'application/json')
                ->post($url);
            
            $this->info("Status: " . $response->status());
            $this->info("Headers: " . json_encode($response->headers(), JSON_PRETTY_PRINT));
            $this->info("Response: " . $response->body());
            
            if ($response->successful()) {
                $json = $response->json();
                if (($json['status'] ?? false) === true) {
                    $this->info("🎉 SUCCESS dengan replicate JS!");
                    $this->info("Token: " . ($json['token'] ?? 'No token'));
                    
                    // Test employee data jika ada token
                    if (isset($json['token'])) {
                        $this->testEmployeeData($nik, $json['token']);
                    }
                    
                    return 0;
                }
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }

        // Test 2: Alternative approach
        $this->info("\n=== Test 2: Alternative HTTP ===");
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post($url, [
                    'username' => $nik,
                    'password' => $password
                ]);
            
            $this->info("Status: " . $response->status());
            $this->info("Response: " . $response->body());
            
            if ($response->successful()) {
                $json = $response->json();
                if (($json['status'] ?? false) === true) {
                    $this->info("🎉 SUCCESS dengan alternative!");
                    return 0;
                }
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }

        $this->warn("Login masih gagal. Cek log untuk detail.");
        return 1;
    }

    private function testEmployeeData($nik, $token)
    {
        $this->info("\n=== Testing Employee Data ===");
        
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->get('http://10.101.0.85/newhris_api/api/pegawai.php', [
                    'nik' => $nik
                ]);
            
            $this->info("Employee API Status: " . $response->status());
            
            if ($response->successful()) {
                $json = $response->json();
                $this->info("Employee Data: " . json_encode($json, JSON_PRETTY_PRINT));
            } else {
                $this->error("Employee API failed: " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("Employee API error: " . $e->getMessage());
        }
    }
}