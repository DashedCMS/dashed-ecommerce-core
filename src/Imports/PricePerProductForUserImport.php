<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\ToCollection;

class PricePerProductForUserImport implements ToArray
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function array(array $rows): void
    {
        unset($rows[0]);

        foreach($rows as $row){
            if($row[2] || $row[3]){
                DB::table('dashed__product_user')->updateOrInsert(
                    [
                        'product_id' => $row[0],
                        'user_id' => $this->user->id,
                    ],
                    [
                        'price' => $row[2],
                        'discount_price' => $row[3],
                    ]
                );
            }else{
                DB::table('dashed__product_user')
                    ->where('product_id', $row[0])
                    ->where('user_id', $this->user->id)
                    ->delete();
            }
        }

    }
}
