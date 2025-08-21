<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewContactMessage;
use App\Mail\ContactFormReceipt;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        $contact = Contact::create($validatedData);


        $recipient = env('CONTACT_FORM_RECIPIENT', 'service@vito1317.com');
        Mail::to($recipient)->send(new NewContactMessage($contact));

        Mail::to($contact->email)->send(new ContactFormReceipt($contact));

        return response()->json([
            'message' => '訊息已成功寄出，我會盡快回覆您！一封確認信副本也已寄到您的信箱。(若未收到，請檢查垃圾郵件夾)',
        ], 201);
    }
}