<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Post;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class PageController extends Controller
{
    public function home(): View
    {
        return view('marketing.home', ['posts' => Post::published()->latest('published_at')->limit(3)->get()]);
    }

    public function about(): View
    {
        return view('marketing.about');
    }

    public function features(): View
    {
        return view('marketing.features');
    }

    public function useCases(): View
    {
        return view('marketing.use-cases');
    }

    public function contact(): View
    {
        return view('marketing.contact');
    }

    public function submitContact(Request $request, SystemSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // Stored first and always. Mail is optional configuration, and an enquiry that only
        // ever existed as an email is lost the moment SMTP is wrong — which is exactly the
        // state a brand new instance is in.
        $message = ContactMessage::create([...$data, 'ip_address' => $request->ip()]);

        if ($support = $settings->get('support_email')) {
            try {
                Mail::to($support)->send(new ContactMessageReceived($message));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Thanks — your message reached us. We will reply to '.$data['email'].'.');
    }
}
