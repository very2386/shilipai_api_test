<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\CustomerReservation;

class AutoCancelReservation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto_cancel_reservation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '系統自動取消預約';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $start = microtime(date('Y-m-d H:i:s'));

        $reservations = CustomerReservation::where('status','N')
                                           ->whereBetween('start',[date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
                                           ->get();
        foreach( $reservations as $reservation ){
            $reservation->status        = 'Y';
            $reservation->check_time    = date('Y-m-d H:i:s');
            $reservation->cancel_status = 'A';
            $reservation->save(); 
        }

        $end = microtime(date('Y-m-d H:i:s'));
        
        $this->info('自動拒絕預約完成'.( $end - $start ));
    }
}
