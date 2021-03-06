<?php


namespace App\Services;


use Carbon\Carbon;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Date;
use App\Contracts\{MovieStore,NesTransactionResult,OkTransanctionResult,TransactionResult};
use App\Models\{Movie, MovieTransaction, Rental};
use Illuminate\Database\DatabaseManager as DB;

/**
 * Class MovieStoreService
 * @package App\Services
 */
class MovieStoreService implements MovieStore
{
    /**
     * @var DB
     */
    protected $db;
    /**
     * @var Auth
     */
    protected $auth;

    /**
     * MovieStoreService constructor.
     * @param DB $db
     * @param Auth $auth
     */
    public function __construct(DB $db, Auth $auth)
    {
        $this->db = $db;
        $this->auth = $auth;
    }

    /**
     * Add a log transaction with for the movie with the reason o buy
     * reduce the stock by 1 if it is the last movie change the availability to false
     * If there is not stock will return a NesTransactionResult
     * @param Movie $movie
     * @return TransactionResult
     * @throws \Throwable
     */
    public function buy(Movie $movie) : TransactionResult
    {
        if($movie->stock <= 0) {
            return new NesTransactionResult();
        }

        try {
            $this->db->beginTransaction();
            $this->validateAndCreateTransaction($movie,MovieTransaction::BUY,$movie->sale_price);
            $movie->save();
            $this->db->commit();
            return new OkTransanctionResult();
        }catch (\Exception $exception){
            $this->db->rollBack();
            return new TransactionResult(false,"something went wrong");
        }
    }

    /**
     * It creates the log transaction for the rental
     * and creates the rental with the expected return date
     *if the user already has an rental pending for this movie
     *won't allow him to create a new one
     * @param Movie $movie
     * @return TransactionResult
     * @throws \Throwable
     */
    public function rent(Movie $movie): TransactionResult
    {
        if($movie->stock <= 0) {
            return new NesTransactionResult();
        }

        try {
            $this->db->beginTransaction();
            $rentals = $movie->rentals()
                ->where('user_id',$this->auth->guard()->id())
                ->where('returned_at',null)
                ->get();

            if($rentals->isNotEmpty()){
                return new TransactionResult(false,"This movie is already rented");
            }
            $this->validateAndCreateTransaction($movie,MovieTransaction::RENTAL,$movie->rental_price);

            $movie->rentals()->save(new Rental([
                'user_id' => $this->auth->guard()->id(),
                'created_at' => now(),
                'expected_return_date' => Carbon::now()->addWeeks(2) // @todo make this configurable
            ]));

            $movie->save();
            $this->db->commit();
            return new OkTransanctionResult();
        }catch (\Exception $exception){
            $this->db->rollBack();
            return new TransactionResult(false,"something went wrong");
        }
    }

    /**
     * This method add new log transaction with the purpose of rental
     * but could add another with reason of penalty if the user return
     * the movie late.
     * If a movie has stock 0 and a User return it will mark the availability as true
     * @param Movie $movie
     * @return TransactionResult
     * @throws \Throwable
     */
    public function return(Movie $movie): TransactionResult
    {
        try {
            $this->db->beginTransaction();

            $rentals = $movie->rentals()
                ->where('user_id', $this->auth->guard()->id())
                ->where("returned_at",null)
                ->get();

            if($rentals->isEmpty()){
                return new TransactionResult(false,"Not rental found");
            }

            $movie->stock += 1;
            if (!$movie->availability) {
                $movie->availability = true;
            }
            $rental = $rentals->first();
            $rental->returned_at = now();

            $result = new OkTransanctionResult();

            if(Carbon::createFromFormat('Y-m-d',$rental->expected_return_date) < Carbon::today()){
                $movie->transactions()->save(new MovieTransaction([
                    "reason" => MovieTransaction::PENALTY,
                    "amount" => 5, // @todo make this configurable
                    "user_id" => $this->auth->guard()->id(),
                    "created_at" => now()
                ]));
                $result = new TransactionResult(true,"You were penalized for late return");
            }
            $rental->save();
            $movie->save();
            $this->db->commit();
            return $result;
        }catch (\Exception $exception){
            $this->db->rollBack();
            return new TransactionResult(false,"something went wrong");
        }
    }

    /**
     * @param Movie $movie
     * @param string $reason
     * @param float $amount
     */
    protected function validateAndCreateTransaction(Movie $movie, string $reason, float $amount)
    {
        $movie->stock -= 1;
        if ($movie->stock === 0 && $movie->availability) {
            $movie->availability = false;
        }

        $movie->transactions()->save(new MovieTransaction([
            "reason" => $reason,
            "amount" => $amount,
            "user_id" => $this->auth->guard()->id(),
            "created_at" => now()
        ]));
    }
}
