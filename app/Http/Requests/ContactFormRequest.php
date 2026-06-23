<?php
// app/Http/Requests/ContactFormRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:100',
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
            'form_started_at' => 'nullable|numeric',
            'g-recaptcha-response' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name',
            'name.max' => 'Name cannot exceed 100 characters',
            'email.required' => 'Please enter your email address',
            'email.email' => 'Please enter a valid email address',
            'email.max' => 'Email cannot exceed 100 characters',
            'phone.max' => 'Phone number cannot exceed 20 characters',
            'company.max' => 'Company name cannot exceed 100 characters',
            'subject.required' => 'Please enter a subject',
            'subject.max' => 'Subject cannot exceed 200 characters',
            'message.required' => 'Please enter your message',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (preg_match('/http|www|\.com|\.net|\.org/i', (string) $this->name)) {
                $validator->errors()->add('name', 'Names cannot contain URLs or website addresses.');
            }

            $linkCount = preg_match_all('/https?:\/\/[^\s]+/', (string) $this->message);
            if ($linkCount > 3) {
                $validator->errors()->add('message', 'Message contains too many links. Please reduce to 3 or fewer.');
            }
        });
    }
}
