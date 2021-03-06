<?php 

namespace App\Http\Controllers;


use App\Models\JobState;
use App\Models\Tool;
use App\Models\Job;
use App\Models\File;
use JWTAuth;
use Illuminate\Http\Request;
use Validaciones\JobRequest;
use Illuminate\Support\Facades\Auth;
use Storage;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Client;


class JobController extends Controller 
{

  /**
   * Display a listing of the resource.
   *
   * @return Response
   */
  protected $jobs;
  protected $states;
  protected $tools;
  protected $file;
  
  
  function __construct (JobState $states,Job $jobs,Tool $tools,File $file){
    
    $this->jobs=$jobs;
    $this->states=$states;
    $this->tools=$tools;
    $this->file=$file;
  }
  public function index(Request $request){

   $jobs = $this->jobs->filters($request->all())->index()->get();
   $tools = $this->tools->all();
   $states = $this->states->all();
   
   $data = ["jobs"=>$jobs,"tools"=>$tools,"states"=>$states];

   return response()
      ->json(compact('data')); 

  }

  /**
   * Show the form for creating a new resource.
   *
   * @return Response
   */
  public function create()
  {


  $tools = $this->tools->all();//->where("idState",2)->get();
   $states = $this->states->all();
   
   $data = ["tools"=>$tools,"states"=>$states];

   return response()
      ->json(compact('data')); 
    
  }

  /**
   * Store a newly created resource in storage.
   *
   * @return Response
   */
  public function store(JobRequest $request)
  {
    
    $job=$this->jobs->fill($request->all());

    $job->name=Auth::user()->code."-".$job->name;
    $job->idInsertUser=Auth::user()->id;
    $job->iteration=1;

    // //delete the before algorithm
    // Storage::disk("jobs")->delete('job-'.$job->id."/iteracion-".$job->iteration."/".$job->algorithm);
    //move algorithms file to jobs folders    
    $job->save();
    $file='jobs/job-0/algorithms/'.$job->algorithm;
    Storage::move($file, "jobs/job-".$job->id."/iteracion-".$job->iteration."/".$job->algorithm);    
    $data = ["job"=>$job,"message"=>"Job Created!"];

    return response()
      ->json(compact('data'));  
    
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return Response
   */
  public function show($id)
  {
    
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return Response
   */
  public function edit($id)
  {
      $job=$this->jobs->findOrFail($id);
      $tools = $this->tools->all();//where("idState",2)->get();
      $states = $this->states->all();
       $data = ["job"=>$job,"tools"=>$tools,"states"=>$states];
       
       return response()->json(compact('data'));
    }

  /**
   * Update the specified resource in storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function update($id,Request $request)
  {


   $job=$this->jobs->findOrFail($id);
   $job= $job->fill($request->all());
   $job->name=Auth::user()->code."-".$job->name;   
    $job->update();
           $data = ["job"=>$job,"message"=>"Updated It!","request"=>$request->all()];
       return response()
      ->json(compact('data'));
    
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function destroy($id)
  {
    $job=$this->jobs->findOrFail($id);
    $job->delete();
    $data = ["job"=>$job,"message"=>"Delete It!"];
    return response()
      ->json(compact('data'));     
  }

  public function showSubmit($id,$iteration,Request $request){
    $job=$this->jobs->findOrFail($id);    
    $job->iteration=$iteration;
    $textFile="executable = ".$job->algorithm
    ."\nuniverse = vanilla"
    ."\nlog = /home/ubuntu/env/h/".$id."/".$iteration."/log.log"
    ."\noutput = /home/ubuntu/env/h/".$id."/".$iteration."/".$job->outPut
    ."\nQueue";

    //create a .submit base
    Storage::disk('jobs')->put('job-'.$job->id."/iteracion-".$job->iteration."/".$job->submitCondor, $textFile);
    $textSubmitCondor=Storage::disk('jobs')->get(
      '/job-'.$job->id."/iteracion-".$job->iteration."/".$job->submitCondor);
    return response()
      ->json(compact('textSubmitCondor'));

  }


  public function sendJob($id,$iteration,Request $request){
    
    $job=$this->jobs->findOrFail($id);
    Storage::disk('jobs')->put('job-'.$job->id."/iteracion-".$iteration."/".$job->submitCondor, 
    $request->get("submitText"));

    sleep(1);   
    $responseFiles=$job->sendFileToExternalServer($iteration); 
    //dd($responseFiles);
    sleep(1);
    $responseJobs=$job->send($iteration);     

    $job->idState=2;
    
    $job->save();

   return response()->json(["message"=>"job send successful, be calm! i notify you when the job finished."]);

  
  }


  public function cancelJob($id){
    $job=$this->jobs->findOrFail($id);
    $job->idState=1;
    $job->save();
    return response()
      ->json(["message"=>"job canceled"]);
  
  }
  

  public function changeAlgorithm($id=null, Request $request){

      if(!is_file($request->file('algorithm'))){
        throw new Exception("The input algorithm must be file", 1);
      }
      //save the new file on disk
    $path=$request->file('algorithm')
    ->storeAs('job-0/algorithms'
              ,$request->file('algorithm')->getClientOriginalName(),"jobs");
    
    $data = ["message"=>"Upload!","path"=>$path,"basename"=>basename($path)];  
       return response()->json(compact('data','path'));
    }

   public function downloadAlgorithm($id){
     $job=$this->jobs->findOrFail($id);
      $file=Storage::disk('jobs')->getDriver()
      ->getAdapter()
      ->applyPathPrefix("/job-".$id."/iteracion-".$job->iteration."/".$job->algorithm);
     return response()->download($file);
   }
   
   public function syncro($id,$iteration){
     $job=$this->jobs->findOrFail($id);
     Storage::disk('jobs')->delete("/job-".$id."/iteracion-".$iteration."/".$job->outPut);
     Storage::disk('jobs')->delete("/job-".$id."/iteracion-".$iteration."/log.log");     
     $job->downloadResults($iteration);
     return response()
      ->json(["message"=>"done!"]);
      

  }
  public function showFile($id,$iteracion,$basename){
    $job=$this->jobs->findOrFail($id);
    $fileText=Storage::disk('jobs')->get(
      '/job-'.$job->id."/iteracion-".$iteracion."/".$basename);
    return response()
      ->json(compact('fileText'));    
  }
  public function newFolder($id){
    $job=$this->jobs->findOrFail($id);
    $job->iteration=$job->iteration+1;    
    $job->copyFolder($job->iteration-1,$job->iteration);
    $job->save();
    return response()
      ->json(["message"=>"done!"]);

 
  }
    
}

?>