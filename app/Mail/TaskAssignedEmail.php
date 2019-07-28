<?php

namespace App\Mail;

use App\User;
use App\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskAssignedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var Order
     */
    public $user;
    public $creator;
    public $task;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user,User $creator,Task $task)
    {
        $this->user = $user;
        $this->creator= $creator;
        $this->task = $task;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.createtask');
    }
}