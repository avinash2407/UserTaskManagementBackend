<?php

namespace App\Jobs;

use App\Mail;

class MailJob extends Job{
	public $mail;
	public function __construct(Mail $mail){
		$this->mail = $mail;
	}
	public function handle(){}
}