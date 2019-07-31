<?php
namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TasksController extends Controller {
	private $request;

	public function createTask(Request $request) {
		$now = Carbon::now();
		$this->validate($request, [
			'title' => 'required|min:3',
			'due_date' => 'required|date|after:now',
			'assignee' => 'required|Integer',
		]);
		$title = $request->input('title');
		$description = $request->input('description');
		$due_date = $request->input('due_date');
		$currentuser = Helper::retuser($request);
		$user = User::find($request->input('assignee'));
		if ($user !== null) {
			if ($currentuser->role === 'Admin') {
				return Helper::createhelper($currentuser->id, $user->id, $title, $description, $due_date);
			} else {
				if ($currentuser->id !== $user->id) {
					return response()->json([
						'error' => 'unauthorized',
					], 401);
				} else {
					return Helper::createhelper($currentuser->id, $user->id, $title, $description, $due_date);
				}
			}
		}
	}
	public function getTaskList(Request $request) {
		$keyassignee = $request->input('keyassignee');
		$keyassignor = $request->input('keyassignor');
		$keytitle = $request->input('keytitle');
		$keystatus = $request->input('keystatus');
		$user = Helper::retuser($request);
		$now = Carbon::now();
		$tasks = Task::where("status", "LIKE", "%$keystatus%")
			->WhereNull('deleted_at')
			->where(function ($query) use ($request) {
				if ($request->input('start_date')) {
					$query->where('created_at', '>=', $request->input('start_date'));
				}
			})
			->where(function ($query) use ($request) {
				if ($request->input('last_date')) {
					$query->where('created_at', '<=', $request->input('last_date'));
				}
			})
			->whereHas('touser', function ($query) use ($keyassignee) {
				$query->where("name", "LIKE", "%$keyassignee%");
			})
			->whereHas('fromuser', function ($query) use ($keyassignor) {
				$query->where("name", "LIKE", "%$keyassignor%");
			})
			->where(function ($query) use ($keytitle, $user) {
				if ($user->role === 'Admin') {
					$query->where(function ($query) use ($keytitle) {
						$query->where("title", "LIKE", "%$keytitle%");
					})
						->orWhere(function ($query) use ($keytitle) {
							$query->WhereNotNull("description")
								->where("description", "LIKE", "%$keytitle%");
						});
				} else {
					$query->where(function ($query) use ($keytitle, $user) {
						$query->where("title", "LIKE", "%$keytitle%")
							->where("assigned_to", $user->id);
					})
						->orWhere(function ($query) use ($keytitle, $user) {
							$query->WhereNotNull("description")
								->where("description", "LIKE", "%$keytitle%")
								->where("assigned_to", $user->id);
						});
				}
			})
			->with('touser')
			->with('fromuser');
		$tasklist = $tasks->paginate(9);
		return response()->json(['listoftasks' => $tasklist], 200);
	}
	public function updateTask(Request $request) {
		$this->validate($request, [
			'taskId' => 'required|exists:tasks,id',
			'due_date' => 'required|date|after:now',
			'title' => 'required',
		]);
		$user = Helper::retuser($request);
		$task = Task::find($request->input('taskId'));
		$userid = $user->id;
		if ($task->deleted_at === null && $task->created_by === $userid) {
			if ($request->input('title')) {
				$task->title = $request->input('title');
			}
			$task->description = $request->input('description');
			if ($request->input('due_date')) {
				$task->due_date = $request->input('due_date');
			}
			$task->save();
			return response()->json([
				'title' => $task->title,
			], 200);
		} elseif ($task->created_by !== $userid) {
			return response()->json([
				'error' => 'Unauthorized',
			], 401);
		} else {
			return response()->json([
				'error' => 'Task already deleted',
			], 400);
		}
	}
	public function updateStatus(Request $request) {
		$this->validate($request, [
			'taskId' => 'required|exists:tasks,id',
			'status' => ['required', Rule::in(['inProgress', 'completed'])],
		]);
		$task = Task::find($request->input('taskId'));
		$now = Carbon::now();
		$user = Helper::retuser($request);
		$userid = $user->id;
		if ($task->status !== null && $task->assigned_to === $userid && $task->status !== 'completed') {
			$task->status = $request->input('status');
			if ($request->input('status') === 'completed') {
				$task->completed_at = Carbon::now();
			}

			$task->save();
			return response()->json([
				'title' => $task->title,
				'status' => $task->status,
			], 200);
		} elseif ($task->status === 'completed' && $request->input('status') !== 'completed') {
			return response()->json([
				'error' => 'Task already marked as completed',
			], 401);
		} elseif ($task->assigned_to !== $userid) {
			return response()->json([
				'error' => 'Unauthorized',
			], 401);
		} else {
			return response()->json([
				'error' => 'Task already deleted',
			], 400);
		}
	}
	public function deleteTask(Request $request) {
		$this->validate($request, [
			'taskId' => 'required|exists:tasks,id',
		]);
		$task = Task::find($request->input('taskId'));
		$user = Helper::retuser($request);
		$userid = $user->id;
		if ($task->deleted_at === null && $task->created_by === $userid) {
			$task->deleted_at = Carbon::now();
			$task->save();
			return response()->json([
				'title' => $task->title,
				'status' => $task->status,
			], 200);
		} elseif ($task->created_by !== $userid) {
			return response()->json([
				'error' => 'Unauthorized',
			], 401);
		} else {
			return response()->json([
				'error' => 'Task already deleted',
			], 400);
		}
	}
	public function sendstats(Request $request) {
		$user = Helper::retuser($request);
		$userid = $user->id;
		$barstats = [];
		$now = Carbon::now();
		$mon = $now->month;
		$year = $now->year;
		$query = Task::select('title', 'description', 'due_date', 'cretated_by')
			->where('assigned_to', $userid)
			->whereNull('deleted_at');
		$query1 = clone $query;
		$query2 = clone $query;
		$query3 = clone $query;
		$query4 = clone $query;
		$query5 = clone $query;
		$query6 = clone $query;
		$total = $query->count();
		$condition = $request->input('condition');
		$compontime = $query1->where('status', 'completed')
			->whereRaw('completed_at <= due_date')
			->count();
		if ($total !== 0) {
			$compontime = $compontime / $total * 100;
		}

		$compaftertime = $query2->where('status', 'completed')
			->whereRaw('completed_at > due_date')
			->count();
		if ($total !== 0) {
			$compaftertime = $compaftertime / $total * 100;
		}

		$inprogress = $query3->where('status', 'inProgress')
			->where('due_date', '>=', $now)
			->count();
		if ($total !== 0) {
			$inprogress = $inprogress / $total * 100;
		}

		$overdue = $query4->where('due_date', '<', $now)
			->where('status', 'inProgress')
			->count();
		$overdue1 = $query5->where('due_date', '<', $now)
			->where('status', 'assigned')
			->count();
		$overdue = $overdue + $overdue1;
		if ($total !== 0) {
			$overdue = $overdue / $total * 100;
		}

		$noaction = $query6->where('due_date', '>=', $now)
			->where('status', 'assigned')
			->count();
		if ($total !== 0) {
			$noaction = $noaction / $total * 100;
		}

		$piestats = [];

		$piestats[0] = [
			'name' => 'compontime',
			'y' => $compontime,
		];
		$piestats[1] = [
			'name' => 'compaftertime',
			'y' => $compaftertime,
		];
		$piestats[2] = [
			'name' => 'inprogress',
			'y' => $inprogress,
		];
		$piestats[3] = [
			'name' => 'overdue',
			'y' => $overdue,
		];
		$piestats[4] = [
			'name' => 'noaction',
			'y' => $noaction,
		];
		$jobj = [];
		if ($condition === "last year") {
			$jobj = Helper::dashboardhelper($year - 1, 12, $userid);
		} elseif ($condition === "this year") {
			$jobj = Helper::dashboardhelper($year, $mon, $userid);
		} else {
			$obj1 = Helper::dashboardhelper($year - 1, 12, $userid);
			$obj2 = Helper::dashboardhelper($year - 2, 12, $userid);
			$jobj = array_merge($obj1, $obj2);
		}
		return response()->json([
			'barstats' => $jobj,
			'piestats' => $piestats,
		], 200);
	}
	public function dashboardtasks(Request $request) {
		$user = Helper::retuser($request);
		$userid = $user->id;
		$tasks = Task::whereHas('fromuser', function ($query) use ($userid) {
			$query->where("id", $userid);
		})
			->with('fromuser')
			->where('status', 'NOT LIKE', '%completed%')
			->whereNull('deleted_at')
			->orderByRaw('due_date ASC')->paginate(2);
		return response()->json(['tasks' => $tasks], 200);
	}
}
