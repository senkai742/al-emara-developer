<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\PasswordResetMail;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    /**
     * Handle login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            Log::info('User logged in via Auth', ['email' => $request->email]);
            return redirect()->route('dashboard');
        }

        return back()
            ->withErrors(['email' => 'Invalid email or password.'])
            ->withInput($request->except('password'));
    }

    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Show the registration form.
     */
    public function showRegisterForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register');
    }

    /**
     * Handle registration request.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);
        Log::info('New user registered', ['email' => $request->email]);

        return redirect()->route('dashboard');
    }

    /**
     * Show the forgot password form.
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $token = Str::random(64);

        cache(['password_reset_' . $token => $user->id], now()->addHour());
        cache(['password_reset_email_' . $request->email => $token], now()->addHour());

        // Debug info collection
        $debugInfo = [];
        $debugInfo[] = "Mailer: " . config('mail.default');
        $debugInfo[] = "Host: " . config('mail.mailers.smtp.host');
        $debugInfo[] = "Port: " . config('mail.mailers.smtp.port');
        $debugInfo[] = "Encryption: " . config('mail.mailers.smtp.encryption');
        $debugInfo[] = "Username: " . config('mail.mailers.smtp.username');
        $debugInfo[] = "From: " . config('mail.from.address');
        $debugInfo[] = "To: " . $user->email;

        try {
            Mail::to($user->email)->send(new PasswordResetMail($token, $user->email));

            $debugInfo[] = "Result: Mail::send() returned without exception";
            $debugInfo[] = "Note: SMTP accepted the message but delivery is not guaranteed";

            return back()
                ->with('status', 'Password reset link has been sent! Check spam folder if not received.')
                ->with('debug', implode(" | ", $debugInfo));
        } catch (\Exception $e) {
            $debugInfo[] = "ERROR: " . $e->getMessage();
            return back()
                ->withErrors(['email' => $e->getMessage()])
                ->with('debug', implode(" | ", $debugInfo));
        }
    }

    /**
     * Show the reset password form.
     */
    public function showResetPasswordForm(Request $request, $token)
    {
        $email = $request->query('email');
        return view('auth.reset-password', ['token' => $token, 'email' => $email]);
    }

    /**
     * Reset the password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $userId = cache('password_reset_' . $request->token);

        if (!$userId) {
            return back()->withErrors(['token' => 'This password reset token is invalid or has expired.']);
        }

        $user = User::find($userId);

        if (!$user || $user->email !== $request->email) {
            return back()->withErrors(['email' => 'We could not find a user with that email address.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Clear the cache
        cache()->forget('password_reset_' . $request->token);
        cache()->forget('password_reset_email_' . $request->email);

        Log::info('Password reset successful', ['email' => $user->email]);

        return redirect()->route('login')->with('success', 'Your password has been reset successfully. Please sign in with your new password.');
    }
}
