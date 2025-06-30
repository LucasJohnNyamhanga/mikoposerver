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
        $today = new DateTime(); // today's date as the cutoff
    
        $loans = Loan::with(['transactions', 'customers'])
            ->whereIn('status', ['approved', 'defaulted'])
            ->get();
    
        foreach ($loans as $loan) {
            if (
                !$loan->issued_date ||
                !$loan->amount ||
                !$loan->riba ||
                !$loan->total_due ||
                !$loan->muda_malipo ||
                !$loan->kipindi_malipo
            ) {
                continue;
            }
    
            $issuedDate = new DateTime($loan->issued_date);
            $installmentAmount = $loan->total_due / $loan->muda_malipo;
    
            // Generate due dates for each installment
            $dueDates = $this->generateInstallmentDueDates($issuedDate, $loan->muda_malipo, $loan->kipindi_malipo);
    
            // Only count installments due after the issued date, and before or on today
            $expectedInstallments = collect($dueDates)
                ->filter(fn($dueDate) => $dueDate > $issuedDate && $dueDate <= $today)
                ->count();
    
            $expected = $expectedInstallments * $installmentAmount;
    
            // Sum of actual payments (up to today)
            $paid = $loan->transactions
                ->where('type', 'kuweka')
                ->where('category', 'rejesho')
                ->filter(fn($tx) => new DateTime($tx->created_at) <= $today)
                ->sum('amount');
    
            if ($expected > $paid) {
                $results[] = [
                    'loan_id' => $loan->id,
                    'expected' => round($expected, 2),
                    'paid' => round($paid, 2),
                    'balance' => round($expected - $paid, 2),
                    'loan_amount' => round($loan->amount, 2),
                    'riba' => round($loan->riba, 2),
                    'repayment_interval' => $loan->kipindi_malipo,
                    'loan_type' => $loan->loan_type,
                    'customers' => $loan->customers->map(function ($customer) {
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
                    })->values(),
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

    public function getLoansWithPendingRejeshoUntilDate(DateTime $endDate): array
    {
        $results = [];

        $loans = Loan::with(['transactions', 'customers'])
            ->whereIn('status', ['approved', 'defaulted'])
            ->get();

        foreach ($loans as $loan) {
            // Skip invalid loans
            if (
                !$loan->issued_date ||
                !$loan->amount ||
                !$loan->riba ||
                !$loan->total_due ||
                !$loan->muda_malipo ||
                !$loan->kipindi_malipo
            ) {
                continue;
            }

            $issuedDate = new DateTime($loan->issued_date);
            $installmentAmount = $loan->total_due / $loan->muda_malipo;

            // Generate due dates
            $dueDates = $this->generateInstallmentDueDates($issuedDate, $loan->muda_malipo, $loan->kipindi_malipo);

            // Count how many installments should have been paid by endDate
            $expectedInstallments = collect($dueDates)
            ->filter(fn($dueDate) => $dueDate > $issuedDate && $dueDate <= $endDate)
            ->count();

            $expected = $expectedInstallments * $installmentAmount;

            // Sum of payments made up to endDate
            $paid = $loan->transactions
                ->where('type', 'kuweka')
                ->where('category', 'rejesho')
                ->filter(function ($tx) use ($endDate) {
                    $date = new DateTime($tx->created_at);
                    return $date <= $endDate;
                })
                ->sum('amount');

            if ($expected > $paid) {
                $results[] = [
                    'loan_id' => $loan->id,
                    'expected' => round($expected, 2),
                    'paid' => round($paid, 2),
                    'balance' => round($expected - $paid, 2),
                    'loan_amount' => round($loan->amount, 2),
                    'riba' => round($loan->riba, 2),
                    'repayment_interval' => $loan->kipindi_malipo,
                    'loan_type' => $loan->loan_type,
                    'customers' => $loan->customers->map(function ($customer) {
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
                    })->values(),
                ];
            }
        }

        return $results;
    }

    public function countDefaultedLoansUntilDate(DateTime $endDate): int
    {
        return Loan::where('status', 'defaulted')
            ->whereDate('issued_date', '<=', $endDate)
            ->count();
    }

    public function getProfitFromActiveLoansUntilDate(DateTime $endDate): float
    {
        $profit = DB::table('loans')
            ->whereIn('status', ['approved', 'defaulted'])
            ->whereDate('issued_date', '<=', $endDate)
            ->selectRaw('SUM(amount * (riba / 100)) as total_profit')
            ->value('total_profit');

        return round($profit ?? 0, 2);
    }


    private function generateInstallmentDueDates(DateTime $start, int $count, string $interval): array
    {
        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            $due = clone $start;

            switch (strtolower($interval)) {
                case 'siku':
                    $due->modify("+$i days");
                    break;
                case 'wiki':
                    $due->modify("+$i weeks");
                    break;
                case 'mwezi':
                    $due->modify("+$i months");
                    break;
                default:
                    throw new InvalidArgumentException("Interval must be siku, wiki, or mwezi");
            }

            $dates[] = $due;
        }

        return $dates;
    }


}
