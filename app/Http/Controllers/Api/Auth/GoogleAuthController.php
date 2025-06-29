<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Traits\ResponseTrait;
use Exception;
use Google\Service\Oauth2;

class GoogleAuthController extends Controller
{
    use ResponseTrait;

    protected $clientService;

    public function __construct(GoogleClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    /**
     * Redirect user to Google OAuth consent screen.
     * JWT token must be passed as Bearer token in Authorization header.
     */
    public function redirect(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized: JWT token required'], 401);
        }

        // Generate unique state to correlate OAuth request and JWT user
        $state = uniqid('state_', true);

        // Store JWT token temporarily for callback (expires in 5 minutes)
        Cache::put($state, $token, now()->addMinutes(5));

        // Prepare Google Client for OAuth
        $client = $this->clientService->createClient();

        // Attach state for security and user mapping
        $client->setState($state);

        // Get Google OAuth consent URL
        $authUrl = $client->createAuthUrl();

        // Redirect user to Google consent screen
        return redirect($authUrl);
    }

    /**
     * Google OAuth callback URL.
     * Exchanges authorization code for tokens and links Google account with authenticated user.
     */
    public function callback(Request $request)
    {
        try {
            $state = $request->get('state');
            $jwt = Cache::pull($state); // Remove cached JWT token for one-time use

            if (!$jwt) {
                return $this->errorResponse('Invalid or expired OAuth session state', 401);
            }

            // Authenticate user using JWT token
            $user = JWTAuth::setToken($jwt)->toUser();

            if (!$user) {
                return $this->errorResponse('User not found for provided token', 404);
            }

            $client = $this->clientService->createClient();

            // Exchange authorization code for access token
            $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));

            if (isset($token['error'])) {
                return $this->errorResponse('Failed to get access token: ' . $token['error'], 400);
            }

            $client->setAccessToken($token);

            // Get Google user info from OAuth2 service
            $oauth2 = new Oauth2($client);
            $googleUser = $oauth2->userinfo->get();

            // Save or update Google OAuth data on your user model
            $user->google_id = $googleUser->id;
            $user->google_token = $token['access_token'];
            if (isset($token['refresh_token'])) {
                $user->google_refresh_token = $token['refresh_token'];
            }
            $user->google_calendar_connected = true;
            $user->save();

            return redirect()->to(config('services.frontend.redirect_url'));


        } catch (Exception $e) {
            return $this->errorResponse('Google authentication failed: ' . $e->getMessage(), 500);
        }
    }

    public function redirectToGoogle(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized: JWT token required'], 401);
        }

        // Generate unique state to correlate OAuth request and JWT user
        $state = uniqid('state_', true);

        // Store JWT token temporarily for callback (expires in 5 minutes)
        Cache::put($state, $token, now()->addMinutes(5));

        // Prepare Google Client for OAuth
        $client = $this->clientService->createClient();

        // Attach state for security and user mapping
        $client->setState($state);

        // Get Google OAuth consent URL
        $authUrl = $client->createAuthUrl();

        return $this->successResponse(
            ['url' => $authUrl],
            'Google authentication URL generated',
            200
        );
    }

    /**
     * Disconnect the authenticated user's Google account.
     */
    public function disconnect(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            $user->google_id = null;
            $user->google_token = null;
            $user->google_refresh_token = null;
            $user->google_calendar_connected = false;
            $user->save();
            return $this->successResponse('Google account disconnected successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Failed to disconnect Google account: ' . $e->getMessage(), 500);
        }
    }
}
