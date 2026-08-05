<?php

namespace App\Http\Controllers\Api;

use App\Cloud\CloudProviderManager;
use App\Cloud\Exceptions\CloudCredentialException;
use App\Http\Controllers\Controller;
use App\Models\CloudAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CloudAccountValidationController extends Controller
{
    public function __invoke(Request $request, CloudProviderManager $providers): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:digitalocean'],
            'token' => ['required', 'string', 'min:20', 'max:255'],
        ]);

        $account = new CloudAccount(['provider' => $data['provider'], 'credentials' => ['token' => $data['token']]]);

        try {
            $providers->for($account)->validateCredentials();
        } catch (CloudCredentialException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage(),
                'provider_status' => $exception->providerStatus,
            ], $exception->httpStatus);
        } catch (ConnectionException) {
            return response()->json(['valid' => false, 'message' => 'Uplary could not reach DigitalOcean over HTTPS.'], 503);
        }

        return response()->json([
            'valid' => true,
            'provider' => 'digitalocean',
            'account_status' => 'active',
            'checks' => ['authentication' => true, 'droplets_read' => true, 'ssh_keys_read' => true],
        ]);
    }
}
