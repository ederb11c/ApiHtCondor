<?php 

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\ToolState;
use Illuminate\Http\Request;
use Validaciones\ToolRequest;
use Illuminate\Support\Facades\Auth;

class ToolController extends Controller 
{

  /**
   * Display a listing of the resource.
   *
   * @return Response
   */

  protected $herramienta;
  protected $toolStates;


  function __construct ( Tool $herramienta,ToolState $toolStates){
    $this->herramienta=$herramienta;
    $this->toolStates=$toolStates;
    
   }
  public function index(Request $request)
  {
    $herramientas = $this->herramienta
    ->Index()->Filters($request->all())->get();
    $toolStates=$this->toolStates->all();
   $data = ["tools"=>$herramientas,'toolStates'=>$toolStates];

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
    $toolStates=$this->toolStates->all();
    $data = ['toolStates'=>$toolStates];

    return response()->json(compact('data')); 
    
    
  }

  /**
   * Store a newly created resource in storage.
   *
   * @return Response
   */
  public function store(ToolRequest $request)
  {
    $herramienta=$this->herramienta->fill($request->all());
    $herramienta->idInsertUser=Auth::user()->id;    
    $herramienta->save();
    $data = ["tool"=>$herramienta,"message"=>"Created It!"];
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
    $tool=$this->herramienta->find($id);
    $states=$this->toolStates->all();
    $data = ["tool"=>$tool,'states'=>$states];
    return response()->json(compact('data'));
  }
  /**
   * Update the specified resource in storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function update($id,ToolRequest $request)
  {
    $herramientas=$this->herramienta->find($id);
    $herramientas= $herramientas->fill($request->all());
    $herramientas->update();
    $data = ["tool"=>$herramientas,"message"=>"Update It!"];
       return response()->json(compact('data'));
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function destroy($id)
  {
    $herramientas=$this->herramienta->findOrFail($id);
    $herramientas->delete();
    $data = ["message"=>"Deleted It!"];
       return response()->json(compact('data'));
  
  }
  
}

?>