<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VideoEffects;
use App\Models\EffectBookmarks;
use Illuminate\Http\Request;
use Validator;
use Storage;
use File;

class EffectBookmarksController extends Controller
{
    // api for add effect bookmark
    public function add_effect_bookmark(Request $request)
    {
        // echo "<pre>"; print_r($request->all()); die();
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'effect_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $request->user_id)->first();
        if (!empty($user_data)) 
        {   
            $videoeffects_data = VideoEffects::where('id',$request->effect_id)->first();
            if (!empty($videoeffects_data)) 
            {
                $effect_bookmark_data = EffectBookmarks::where(['user_id' => $request->user_id,'effect_id' => $request->effect_id])->first();
                if (empty($effect_bookmark_data)) 
                {
                    $effectbookmark               = new EffectBookmarks();
                    $effectbookmark->user_id      = $request->user_id;
                    $effectbookmark->effect_id    = $request->effect_id;
                    $effectbookmark->save(); 

                    return response()->json(['msg'=>'Effect bookmark add successfully', 'status' =>'1']);
                }
                else
                {
                    return response()->json(['msg'=>'This effect already added bookmark', 'status' =>'0']);
                }
            }
            else
            {
                return response()->json(['msg'=>'This effect not found..!.', 'status' =>'0']);
            }
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for remove effect bookmark
    public function remove_effect_bookmark(Request $request)
    {
        // echo "<pre>"; print_r($request->all()); die();
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'effect_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $effect_bookmark_data = EffectBookmarks::where(['user_id' => $request->user_id,'effect_id' => $request->effect_id])->first();
        if (!empty($effect_bookmark_data)) 
        {
            EffectBookmarks::where(['user_id' => $request->user_id,'effect_id' => $request->effect_id])->delete();
            return response()->json(['msg'=>'Effect bookmark remove successfully', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'Effect bookmark data not found..!', 'status' =>'0']);
        }
    }

    // api for effect bookmark list
    public function get_effect_bookmarks(Request $request) {

        $result  = array();
        $total_videos = 0;


        $validator = Validator::make($request->all(), [ 
            'user_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $effect_bookmark_data = EffectBookmarks::leftJoin('video_effects','video_effects.id','=','effect_bookmarks.effect_id')
                                ->select("effect_bookmarks.*","video_effects.*","effect_bookmarks.id as id")
                                ->where('effect_bookmarks.user_id','=',$request->user_id)
                                ->orderBy('video_effects.id','DESC')
                                ->get();
        // echo "<pre>"; print_r($effect_bookmark_data->toArray()); die();
        if (count($effect_bookmark_data) > 0) 
        {  
            foreach ($effect_bookmark_data as $row) {
                // $total_videos = Videos::where('song_id','=',$row->song_id)->count();
                 // song file
                if ($row->attachment != '') 
                {
                    $destinationPath =  Storage::disk('public')->path('uploads/effects');
                    if(!empty($row->attachment)){
                        if(File::exists($destinationPath.'/'.$row->attachment)) {
                            $attachment = url('storage/app/public/uploads/effects/'.$row->attachment);
                        }
                        else
                        {
                            $attachment = "";
                        }
                    }
                    else
                    {
                        $attachment = "";
                    }
                }
                else
                {
                    $attachment = "";
                }

                $record[] = array(
                    "id"                    => $row->id,
                    "name"                  => $row->name,
                    "attachment"            => $attachment,
                    "total_effect"          => $total_videos,
                );
                $result = $record;
            }
            return response()->json(['data' => $result,'msg'=>'Effect favortie to remove successfully!', 'status' =>'1']);
        }
        return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
    }
}
