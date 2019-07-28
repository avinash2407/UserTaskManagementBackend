<p> Hello there {{$user->name}} you got some work to do!! </p>
<p> You have been assigned a task by {{$creator->name}}</p>
<p> Task Details <ul> <li>Title:{{$task->title}}</li>
	<li>Description:{{$task->description}}</li>
	<li>Due date:{{$task->due_date}}</li></ul></p>