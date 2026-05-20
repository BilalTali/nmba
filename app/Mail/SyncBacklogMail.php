<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SyncBacklogMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $bodyText;
    public string $subjectText;

    public function __construct(string $bodyText, string $subjectText)
    {
        $this->bodyText = $bodyText;
        $this->subjectText = $subjectText;
    }

    public function build(): self
    {
        return $this->html('<pre>' . e($this->bodyText) . '</pre>')
                    ->subject($this->subjectText);
    }
}
