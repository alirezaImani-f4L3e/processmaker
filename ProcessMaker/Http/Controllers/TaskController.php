<?php

namespace ProcessMaker\Http\Controllers;

use Carbon\Carbon;
use ProcessMaker\Models\User;
use ProcessMaker\Models\Media;
use ProcessMaker\Models\Screen;
use ProcessMaker\Models\Comment;
use ProcessMaker\Models\Notification;
use ProcessMaker\Managers\DataManager;
use Illuminate\Support\Facades\Request;
use ProcessMaker\Models\ProcessRequestToken;
use ProcessMaker\Traits\HasControllerAddons;
use ProcessMaker\Events\ScreenBuilderStarting;
use ProcessMaker\Managers\ScreenBuilderManager;
use ProcessMaker\Traits\SearchAutocompleteTrait;
use ProcessMaker\Nayra\Contracts\Bpmn\ScriptTaskInterface;

class TaskController extends Controller
{
    use SearchAutocompleteTrait;
    use HasControllerAddons;

    private static $dueLabels = [
        'open' => 'Due',
        'completed' => 'Completed',
        'overdue' => 'Due',
    ];

    public function index()
    {
        $title = 'To Do Tasks';

        if (Request::input('status') == 'CLOSED') {
            $title = 'Completed Tasks';
        }

        return view('tasks.index', compact('title'));
    }

    public function edit(ProcessRequestToken $task)
    {
        $task = $task->loadTokenInstance();
        $dataManager = new DataManager();
        $userHasComments = Comment::where('commentable_type', ProcessRequestToken::class)
                                    ->where('commentable_id', $task->id)
                                    ->where('body', 'like', '%{{' . \Auth::user()->id . '}}%')
                                    ->count() > 0;

        if (!\Auth::user()->can('update', $task) && !$userHasComments) {
            $this->authorize('update', $task);
        }

        //Mark as unread any not read notification for the task
        Notification::where('data->url', '/' . Request::path())
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);

        $manager = app(ScreenBuilderManager::class);
        event(new ScreenBuilderStarting($manager, $task->getScreenVersion() ? $task->getScreenVersion()->type : 'FORM'));

        $submitUrl = route('api.tasks.update', $task->id);
        $task->processRequest;
        $task->user;
        $screenVersion = $task->getScreenVersion();
        $task->component = $screenVersion ? $screenVersion->parent->renderComponent() : null;
        $task->screen = $screenVersion ? $screenVersion->toArray() : null;
        $task->request_data = $dataManager->getData($task);
        $task->bpmn_tag_name = $task->getBpmnDefinition()->localName;
        $interstitial = $task->getInterstitial();
        $task->interstitial_screen = $interstitial['interstitial_screen'];
        $task->allow_interstitial = $interstitial['allow_interstitial'];
        $task->definition = $task->getDefinition();
        $task->requestor = $task->processRequest->user;
        $element = $task->getDefinition(true);

        if ($element instanceof ScriptTaskInterface) {
            return redirect(route('requests.show', ['request' => $task->processRequest->getKey()]));
        } else {
            // Get all processes and subprocesses request token id's ..
            $requestTokenIds = $task->processRequest->collaboration->requests->pluck('id');

            // Get all files for process and all subprocesses ..
            $files = Media::whereIn('model_id', $requestTokenIds)->get();

            return view('tasks.edit', [
                'task' => $task,
                'dueLabels' => self::$dueLabels,
                'manager' => $manager,
                'submitUrl' => $submitUrl,
                'files' => $files,
                'addons' => $this->getPluginAddons('edit', []),
                'assignedToAddons' => $this->getPluginAddons('edit.assignedTo', []),
            ]);
        }
    }
}
