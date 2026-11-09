<?php

namespace App\Services\Recruitment;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

final class LaravelMailTransport
{
    public static function send(array $message): string
    {
        if (! config('recruitment.mail_enabled') || config('mail.default') !== 'smtp') {
            throw new \RuntimeException('Email sending is currently unavailable.');
        }
        $text = nl2br(htmlspecialchars($message['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $text = preg_replace_callback('~https?://[^\s<>]+~u', static fn (array $match): string => '<a href="'.$match[0].'" style="color:#006fb4">'.$match[0].'</a>', $text);
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"></head><body style="margin:0;background:#f3f6fb;font-family:Arial,sans-serif;color:#12203f">'
            .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #e3e8f2;border-radius:12px">'
            .'<tr><td style="padding:28px 28px 20px;border-bottom:3px solid #00a6a6"><img src="cid:apec-homes-logo@recruitment" width="240" alt="APEC Homes — Building Lives" style="display:block;width:240px;max-width:100%;height:auto"></td></tr>'
            .'<tr><td style="padding:28px;font-size:15px;line-height:1.7;overflow-wrap:anywhere">'.$text.'</td></tr>'
            .'<tr><td style="padding:18px 28px;border-top:1px solid #e3e8f2;font-size:12px;line-height:1.6;color:#65758c"><strong style="color:#082e82">APEC Homes</strong><br>HR Recruitment</td></tr>'
            .'</table></td></tr></table></body></html>';

        $sent = Mail::html($html, function (Message $mail) use ($message): void {
            $mail->to($message['recipient_email'], $message['recipient_name'])->subject($message['subject']);
            $mail->getSymfonyMessage()->text($message['body']."\n\nAPEC Homes\nHR Recruitment");
            $mail->getSymfonyMessage()->addPart((new DataPart(
                new File(public_path('assets/apec-homes-logo.png')), 'apec-homes.png', 'image/png'
            ))->asInline()->setContentId('apec-homes-logo@recruitment'));
        });
        if (! $sent) {
            throw new \RuntimeException('The email service did not accept the message.');
        }

        return $sent->getMessageId();
    }
}
