<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Events\UserSubscribed;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password as FacadesPassword;
use Illuminate\Http\RedirectResponse;

class AuthController extends Controller
{
    //Register or Create New User
    public function register(RegisterRequest $request): RedirectResponse
    {
        $fields = $request->validated();

        //Create User
        $user = User::create($fields);
        //Login User
        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();

            event(new Registered($user));

            if ($request->subscribe) {
                event(new UserSubscribed($user));
            }
        } else {
            //Redirect User
            return redirect()->back()->with('error', 'مشکلی در ثبت نام شما پیش آمده است مجددا اقدام کنید!');
        }

        //Redirect User
        return redirect()->route('home')->withErrors([
            'success' => auth()->user()->name . ' عزیز خوش آمدید.'
        ]);

    }

    public function login(LoginRequest $request): RedirectResponse
    {

        $credentials = $request->validated();
        if (Auth::attempt($credentials, $request->remember)) {
            $request->session()->regenerate();
            return redirect()->route('home')->withErrors([
                'success' => auth()->user()->name . ' خوش آمدید.'
            ]);
        } else {
            Session::regenerate();
            return redirect()->back()->with('error',
                'نام کاربری یا کلمه عبور شما اشتباه است!'
            );
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->withErrors(['success' => 'به امید دیدار مجدد شما']);
    }

    public function sendResetPasswordLink(ForgotPasswordRequest $request) :RedirectResponse
    {

        $status = FacadesPassword::sendResetLink(
            $request->validated()
        );

        return $status === FacadesPassword::RESET_LINK_SENT
            ? back()->with([
                'success' => 'لینک بازیابی کلمه عبور به ایمیل شما ارسال شد.'
            ])
            : back()->withErrors([
                'error' => 'برای دریافت مجدد لینک بازیابی کلمه عبور چند لحظه صبر کنید'
            ]);
    }

    public function resetPasswordToken(string $token)
    {
        return view('auth.reset-password',['token'=>$token]);
    }

    public function passwordUpdate(ResetPasswordRequest $request): RedirectResponse
    {

        $status = FacadesPassword::reset(
            $request->validated(),

            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));
                $user->save();
                //event(new PasswordReset($user));
            }
        );
        return $status === FacadesPassword::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'کلمه عبور شما تغیر کرد اکنون می توانید با آن در سایت لاگین نمایید.')
            : back()->withErrors(['error' => 'توکن بازیابی کلمه عبور معتبر نمی باشد!']);
    }
}
