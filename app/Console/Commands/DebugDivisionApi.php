<?php
// app/Console/Commands/DebugDivisionApi.php - Fixed version dengan validation

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WorkingApiService;
use Illuminate\Support\Facades\Http;

class DebugDivisionApi extends Command
{
    protected $signature = 'debug:division-api {--nik= : Admin NIK} {--password= : Admin Password}';
    protected $description = 'Debug division API step by step';

    protected $workingApiService;

    public function __construct(WorkingApiService $workingApiService)
    {
        parent::__construct();
        $this->workingApiService = $workingApiService;
    }

    public function handle()
    {
        $this->info('🔍 Debug Division API - Step by Step');
        $this->line(str_repeat('=', 60));

        // Get credentials
        $adminNik = $this->getAdminNik();
        $adminPassword = $this->getAdminPassword();

        if (!$adminNik || !$adminPassword) {
            $this->error('❌ Missing admin credentials!');
            $this->line('Please provide credentials via options or configure in .env file');
            $this->line('Usage: php artisan debug:division-api --nik=admin --password=admin123');
            return 1;
        }

        // Step 1: Test Login
        $this->testLogin($adminNik, $adminPassword);
        
        // Step 2: Test Division API (only if login successful)
        if (isset($this->token)) {
            $this->testDivisionApi();
        }

        return 0;
    }

    protected function getAdminNik(): ?string
    {
        // Priority: command option > .env config > ask user
        if ($this->option('nik')) {
            return $this->option('nik');
        }

        $configNik = config('app.admin_nik');
        if ($configNik && $configNik !== 'admin') {
            return $configNik;
        }

        return $this->ask('Enter admin NIK for API access');
    }

    protected function getAdminPassword(): ?string
    {
        // Priority: command option > .env config > ask user
        if ($this->option('password')) {
            return $this->option('password');
        }

        $configPassword = config('app.admin_password');
        if ($configPassword && $configPassword !== 'admin123') {
            return $configPassword;
        }

        return $this->secret('Enter admin password for API access');
    }

    protected function testLogin(string $adminNik, string $adminPassword): void
    {
        $this->info('Step 1: Testing Login API');
        $this->line('URL: http://10.101.0.85/newhris_api/api/login2.php');
        $this->line("NIK: {$adminNik}");
        $this->line('Password: ' . str_repeat('*', strlen($adminPassword)));
        $this->line('');

        $this->line('🔄 Attempting login...');
        $result = $this->workingApiService->login($adminNik, $adminPassword);
        
        if ($result['success']) {
            $this->info('✅ Login successful!');
            $token = $result['token'];
            $this->line('Token length: ' . strlen($token));
            $this->line('Token preview: ' . substr($token, 0, 20) . '...');
            
            // Show user data if available
            if (isset($result['data']) && !empty($result['data'])) {
                $this->line('');
                $this->info('User data received:');
                $userData = $result['data'];
                foreach ($userData as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        $this->line("  {$key}: {$value}");
                    } else {
                        $this->line("  {$key}: " . gettype($value));
                    }
                }
            }
            
            $this->line('');
            
            // Store token for next step
            $this->token = $token;
        } else {
            $this->error('❌ Login failed!');
            $this->line('Error: ' . $result['message']);
            $this->line('');
            
            // Show possible reasons
            $this->warn('Possible reasons:');
            $this->line('• Invalid NIK or password');
            $this->line('• API server is down');
            $this->line('• Network connectivity issues');
            $this->line('• API endpoint has changed');
            return;
        }
    }

    protected function testDivisionApi(): void
    {
        $this->info('Step 2: Testing Division API');
        $url = 'http://10.101.0.85/newhris_api/api/divisi.php';
        $this->line("URL: {$url}");
        $this->line('');

        try {
            $this->line('🔄 Making API request...');
            
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            $this->line('Response Status: ' . $response->status());
            
            // Show headers (optional, but useful for debugging)
            if ($this->option('verbose')) {
                $this->line('Response Headers:');
                foreach ($response->headers() as $key => $values) {
                    $this->line("  {$key}: " . implode(', ', $values));
                }
            }
            $this->line('');

            $body = $response->body();
            $bodyLength = strlen($body);
            $this->line("Response Body Length: {$bodyLength} characters");
            
            if ($bodyLength > 0) {
                $this->line('Response Body Preview (first 500 chars):');
                $this->line('"' . substr($body, 0, 500) . '"');
                $this->line('');
            }

            if ($response->successful()) {
                $this->info('✅ API call successful!');
                
                // Try to parse JSON
                try {
                    $json = $response->json();
                    $this->analyzeJsonResponse($json);
                } catch (\Exception $e) {
                    $this->error('❌ Failed to parse JSON response');
                    $this->line('JSON Error: ' . $e->getMessage());
                    $this->line('Raw response: ' . $body);
                }
                
            } else {
                $this->error('❌ API call failed!');
                $this->line('Status: ' . $response->status());
                $this->line('Body: ' . $body);
                
                if ($response->status() === 401) {
                    $this->warn('💡 This might be an authentication issue');
                    $this->line('• Token might be expired');
                    $this->line('• Token format might be incorrect');
                    $this->line('• API might require different auth method');
                } elseif ($response->status() === 404) {
                    $this->warn('💡 API endpoint not found');
                    $this->line('• Check if the URL is correct');
                    $this->line('• API might be using different endpoint');
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Exception occurred!');
            $this->line('Error: ' . $e->getMessage());
            $this->line('Error Type: ' . get_class($e));
        }
    }

    protected function analyzeJsonResponse($json): void
    {
        $this->line('Response Analysis:');
        $this->line('Data type: ' . gettype($json));
        
        if (is_array($json)) {
            $this->line('Array length: ' . count($json));
            
            // Check if it's a wrapped response
            if (isset($json['status'])) {
                $this->line('Wrapped response detected:');
                $this->line('  Status: ' . ($json['status'] ? 'true' : 'false'));
                
                if (isset($json['message'])) {
                    $this->line('  Message: ' . $json['message']);
                }
                
                if (isset($json['data'])) {
                    $data = $json['data'];
                    $this->line('  Data type: ' . gettype($data));
                    
                    if (is_array($data)) {
                        $this->line('  Data count: ' . count($data));
                        $this->showFirstDivisionSample($data);
                    }
                }
                
            } else {
                // Direct array response
                $this->line('Direct array response');
                $this->showFirstDivisionSample($json);
            }
            
        } else {
            $this->line('Non-array response: ' . json_encode($json));
        }
    }

    protected function showFirstDivisionSample(array $divisions): void
    {
        if (empty($divisions)) {
            $this->warn('No divisions found in response');
            return;
        }

        $firstDivision = $divisions[0];
        $this->line('');
        $this->info('First division sample:');
        
        foreach ($firstDivision as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $this->line("  {$key}: {$value}");
            } else {
                $this->line("  {$key}: " . gettype($value) . ' (' . json_encode($value) . ')');
            }
        }
        
        // Suggest field mappings
        $this->line('');
        $this->info('Field mapping suggestions:');
        $this->suggestFieldMappings($firstDivision);
    }

    protected function suggestFieldMappings(array $division): void
    {
        $codeFields = ['division_code', 'divisicode', 'code', 'kode_divisi'];
        $nameFields = ['division_name', 'divisiname', 'name', 'nama_divisi'];
        $direktoratFields = ['direktorat', 'directorate', 'direktorat_name'];

        foreach ($codeFields as $field) {
            if (isset($division[$field])) {
                $this->line("  Code field: {$field}");
                break;
            }
        }

        foreach ($nameFields as $field) {
            if (isset($division[$field])) {
                $this->line("  Name field: {$field}");
                break;
            }
        }

        foreach ($direktoratFields as $field) {
            if (isset($division[$field])) {
                $this->line("  Direktorat field: {$field}");
                break;
            }
        }
    }
}