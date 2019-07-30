<?php

namespace App\Helpers;

use App\Mail\TaskAssignedEmail;
use App\Task;
use App\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Mail;

class Helper
{
    public static function createhelper($assignor, $assignee, $title, $description, $due_date)
    {
        $task = new \App\Task;
        $task->title = $title;
        $task->description = $description;
        $task->due_date = $due_date;
        $task->created_by = $assignor;
        $task->assigned_to = $assignee;
        $task->created_at = Carbon::now();
        $task->save();
        $user = User::find($assignee);
        $creator = User::find($assignor);
        Mail::to($user->email)->queue(new TaskAssignedEmail($user, $creator, $task));
        return response()->json([
            'title' => $task->title,
            'description' => $task->description,
        ], 201);
    }
    public static function dashboardhelper($year, $month, $userId, $total)
    {
        $obj = [];
        $now = Carbon::now();
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        for ($i = 0; $i < $month; $i++) {
            $tasks1 = Task::select('title', 'description', 'due_date', 'created_by')->where('assigned_to', $userId)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $i + 1);
            $tasks2 = clone $tasks1;
            $tasks3 = clone $tasks1;
            $tasks4 = clone $tasks1;
            $tasks5 = clone $tasks1;
            $tasks6 = clone $tasks1;
            $compontime = $tasks1
                ->where('status', 'completed')
                ->whereRaw('completed_at <= due_date')
                ->count();
            if ($total !== 0) {
                $compontime = $compontime / $total * 100;
            }

            $compaftertime = $tasks2
                ->where('status', 'completed')
                ->whereRaw('completed_at > due_date')
                ->count();
            if ($total !== 0) {
                $compaftertime = $compaftertime / $total * 100;
            }

            $goodprogress = $tasks3
                ->where('status', 'inProgress')
                ->where('due_date', '>=', $now)
                ->count();
            if ($total !== 0) {
                $goodprogress = $goodprogress / $total * 100;
            }

            $overdue = $tasks4
                ->where('status', 'inProgress')
                ->where('due_date', '<', $now)
                ->count();
            if ($total !== 0) {
                $overdue = $overdue / $total * 100;
            }

            $noaction = $tasks5
                ->where('status', 'assigned')
                ->where('due_date', '>=', $now)
                ->count();
            if ($total !== 0) {
                $noaction = $noaction / $total * 100;
            }

            $noactionoverdue = $tasks6
                ->where('status', 'assigned')
                ->where('due_date', '<', $now)
                ->count();
            if ($total !== 0) {
                $noactionoverdue = $noactionoverdue / $total * 100;
            }

            $overdue = $overdue + $noactionoverdue;

            $obj[$i] = [
                'year' => $year,
                'month' => $months[$i],
                'compontime' => $compontime,
                'compaftertime' => $compaftertime,
                'goodprogress' => $goodprogress,
                'overdue' => $overdue,
                'noaction' => $noaction,
            ];
        }
        return $obj;
    }
    public static function retuser($request)
    {
        $token = $request->cookie('tokencookie');
        $results = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        $user = User::find($results->sub);
        return $user;
    }
    public static function checkRole($request)
    {
        $token = $request->input('token');
        if ($token !== null) {
            $results = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
            $user = User::where('id', $results->sub)->whereNull('deleted_by')->first();
            if ($user !== null) {
                $role = $user->role;
                if ($role != "Admin") {
                    return false;
                }
                return true;
            }
        }
        return false;
    }
    public static function checkmailtoken($request)
    {
        $token = $request->cookie('mailcookie');
        if ($token !== null) {
            $results = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
            $user = User::find($results->sub);
            if ($results->ide !== 'mail') {
                return false;
            }
        }
        return true;
    }
}
