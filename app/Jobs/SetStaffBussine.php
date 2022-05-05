<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Http\Controllers\Controller;
use App\Models\ShopBusinessHour;

class SetStaffBussine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $insert,$staff,$shop_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($insert,$staff,$shop_id)
    {
        $this->insert  = $insert;
        $this->staff   = $staff;
        $this->shop_id = $shop_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $insert  = $this->insert;
        $staff   = $this->staff;
        $shop_id = $this->shop_id;

        for( $i = 1 ; $i <= 7 ; $i++ ){
            $staff_bussiness = ShopBusinessHour::where('shop_staff_id',$staff->id)->where('week',$i)->first();
            if( !$staff_bussiness ){
                foreach( $insert as $in_data ){
                    if( $in_data['week'] == $i ){
                        $data = new ShopBusinessHour;
                        $data->shop_id       = $shop_id;
                        $data->type          = 0;
                        $data->week          = $i;
                        $data->shop_staff_id = $staff->id;
                        $data->start         = $in_data['start'];
                        $data->end           = $in_data['end'];
                        $data->save();
                    }
                }
            }else{
                // 同預設
                if( $staff_bussiness->type == 0 ){
                    ShopBusinessHour::where('shop_staff_id',$staff->id)
                                                    ->where('week',$i)
                                                    ->delete();
                    foreach( $insert as $in_data ){
                        if( $in_data['week'] == $i ){
                            $data = new ShopBusinessHour;
                            $data->shop_id       = $shop_id;
                            $data->type          = 0;
                            $data->week          = $i;
                            $data->shop_staff_id = $staff->id;
                            $data->start         = $in_data['start'];
                            $data->end           = $in_data['end'];
                            $data->save();
                        }
                    }

                }
            }
        }
    }
}
