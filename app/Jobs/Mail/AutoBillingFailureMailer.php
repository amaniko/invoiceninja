<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Mail;

use App\Libraries\MultiDB;
use App\Mail\Admin\AutoBillingFailureObject;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\PaymentFailureObject;
use App\Models\User;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class AutoBillingFailureMailer extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UserNotifies;

    public $client;

    public $error;

    public $company;

    public $payment_hash;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct($client, $error, $company, $payment_hash)
    {
        $this->client = $client;

        $this->error = $error;

        $this->company = $company;

        $this->payment_hash = $payment_hash;

        $this->company = $company;

        $this->settings = $client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        /*If we are migrating data we don't want to fire these notification*/
        if ($this->company->is_disabled) {
            return true;
        }
        
        //Set DB
        MultiDB::setDb($this->company->db);

        //if we need to set an email driver do it now
        $this->setMailDriver();

        //iterate through company_users
        $this->company->company_users->each(function ($company_user) {        

            //determine if this user has the right permissions
            $methods = $this->findCompanyUserNotificationType($company_user, ['payment_failure','all_notifications']);

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $mail_obj = (new AutoBillingFailureObject($this->client, $this->error, $this->company, $this->payment_hash))->build();
                $mail_obj->from = [config('mail.from.address'), config('mail.from.name')];

                //send email
                try {
                    Mail::to($company_user->user->email)
                        ->send(new EntityNotificationMailer($mail_obj));
                } catch (\Exception $e) {
                    //$this->failed($e);
                    $this->logMailError($e->getMessage(), $this->client);
                }
            }
        });
    }
}