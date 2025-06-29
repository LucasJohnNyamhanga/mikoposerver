<?php

namespace App\Services;

use App\Models\Loan;
use DateTime;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanService
{
    /**
     * Calculate repayment amount expected by currentDate
     * 
     * @param DateTime $startDate - loan issue date
     * @param DateTime $currentDate - usually today
     * @param int $duration - total repayment periods (days/weeks/months)
     * @param string $interval - 'siku', 'wiki', or 'mwezi'
     * @param float $totalDue - total amount due to repay (principal + interest)
     * @return float
     */
    public function calculateRepaymentAmount(DateTime $startDate, DateTime $currentDate, int $duration, string $interval, float $totalDue): float
    {
        $normalizedStartDate = new DateTime($startDate->format('Y-m-d'));
        $normalizedCurrentDate = new DateTime($currentDate->format('Y-m-d'));

        if ($normalizedCurrentDate < $normalizedStartDate) {
            return 0.0;
        }

        $repayments = 0;

        switch (strtolower($interval)) {
            case 'siku':
                $firstRepaymentDate = (clone $normalizedStartDate)->modify('+1 day');
                if ($normalizedCurrentDate < $firstRepaymentDate) return 0.0;
                $repayments = $firstRepaymentDate->diff($normalizedCurrentDate)->days + 1;
                break;

            case 'wiki':
                $firstRepaymentDate = (clone $normalizedStartDate)->modify('+7 days');
                if ($normalizedCurrentDate < $firstRepaymentDate) return 0.0;
                $repayments = intdiv($firstRepaymentDate->diff($normalizedCurrentDate)->days, 7) + 1;
                break;

            case 'mwezi':
                $firstRepaymentDate = (clone $normalizedStartDate)->modify('+1 month');
                if ($normalizedCurrentDate < $firstRepaymentDate) return 0.0;
                $repayments = ($normalizedCurrentDate->format('Y') - $firstRepaymentDate->format('Y')) * 12;
                $repayments += $normalizedCurrentDate->format('n') - $firstRepaymentDate->format('n');
                if ($normalizedCurrentDate->format('d') >= $firstRepaymentDate->format('d')) {
                    $repayments += 1;
                }
                break;

            default:
                throw new InvalidArgumentException("Invalid interval: must be 'siku', 'wiki', or 'mwezi'");
        }

        // Cap repayments at duration
        $repayments = min($repayments, $duration);

        // Amount per installment
        $installmentAmount = $totalDue / $duration;

        return $repayments * $installmentAmount;
    }

    /**
     * Get all active loans with customers who are behind on repayments
     * Returns array of loans with repayment info and full customers list
     * 
     * @return array
     */
    public function getLoansWithPendingRejesho(): array
    {
        $results = [];

        $loans = Loan::with(['transactions', 'customers'])
            ->where('status', 'active')
            ->get();

        foreach ($loans as $loan) {
            // Validate required fields
            if (
                !$loan->issued_date ||
                !$loan->amount ||
                !$loan->riba || // interest rate as percentage (e.g. 10 means 10%)
                !$loan->total_due ||
                !$loan->muda_malipo ||
                !$loan->kipindi_malipo
            ) {
                continue;
            }

            $expected = $this->calculateRepaymentAmount(
                new DateTime($loan->issued_date),
                new DateTime(), // today
                $loan->muda_malipo,
                $loan->kipindi_malipo,
                $loan->total_due
            );

            // Sum all payments made with type='kuweka' and category='rejesho'
            $paid = $loan->transactions
                ->where('type', 'kuweka')
                ->where('category', 'rejesho')
                ->sum('amount');

            if ($expected > $paid) {
                $results[] = [
                    'loan_id' => $loan->id,
                    'expected' => round($expected, 2),
                    'paid' => round($paid, 2),
                    'balance' => round($expected - $paid, 2),
                    'loan_amount' => round($loan->amount, 2),   // principal only
                    'riba' => round($loan->riba, 2),            // interest percentage
                    'repayment_interval' => $loan->kipindi_malipo,
                    'loan_type' => $loan->loan_type,             // 'kikundi' or 'binafsi'
                    'customers' => $loan->customers->map(function($customer) {
                        // Optionally, select fields or return full model attributes
                        return [
                            'id' => $customer->id,
                            'jina' => $customer->jina,
                            'jinaMaarufu' => $customer->jinaMaarufu,
                            'jinsia' => $customer->jinsia,
                            'anapoishi' => $customer->anapoishi,
                            'simu' => $customer->simu,
                            'kazi' => $customer->kazi,
                            'picha' => $customer->picha,
                            'ofisi_id' => $customer->ofisi_id,
                            'user_id' => $customer->user_id,
                        ];
                    }),
                ];
            }
        }

        return $results;
    }

    public function countDefaultedLoans(): int
    {
        return Loan::where('status', 'defaulted')->count();
    }

    public function getProfitFromActiveLoans(): float
    {
        $profit = DB::table('loans')
            ->whereIn('status', ['approved', 'defaulted'])
            ->selectRaw('SUM(amount * (riba / 100)) as total_profit')
            ->value('total_profit');

        return round($profit ?? 0, 2); // in case result is null
    }
}
