<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Videos;
use App\Models\Hashtag;
use App\Models\HashtagBookmarks;
use Illuminate\Http\Request;
use Validator;

class HashtagBookmarksController extends Controller
{
    // api for add hashtag bookmark
    public function add_hashtag_bookmark(Request $request)
    {
        // echo "<pre>"; print_r($request->all()); die();
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'hashtag_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $request->user_id)->first();
        if (!empty($user_data)) 
        {   
            $hashtag_data = hashtag::where('id',$request->hashtag_id)->first();
            if (!empty($hashtag_data)) 
            {
                $sound_bookmark_data = HashtagBookmarks::where(['user_id' => $request->user_id,'hashtag_id' => $request->hashtag_id])->first();
                if (empty($sound_bookmark_data)) 
                {
                    $hashtagbookmarks             = new HashtagBookmarks();
                    $hashtagbookmarks->user_id    = $request->user_id;
                    $hashtagbookmarks->hashtag_id = $request->hashtag_id;
                    $hashtagbookmarks->save(); 

                    return response()->json(['msg'=>'hashtag bookmark add successfully', 'status' =>'1']);
                }
                else
                {
                    return response()->json(['msg'=>'This hashtag already added bookmark', 'status' =>'0']);
                }
            }
            else
            {
                return response()->json(['msg'=>'This hashtag not found..!.', 'status' =>'0']);
            }
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for remove hashtag bookmark
    public function remove_hashtag_bookmark(Request $request)
    {
        // echo "<pre>"; print_r($request->all()); die();
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'hashtag_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $sound_bookmark_data = HashtagBookmarks::where(['user_id' => $request->user_id,'hashtag_id' => $request->hashtag_id])->first();
        if (!empty($sound_bookmark_data)) 
        {
            HashtagBookmarks::where(['user_id' => $request->user_id,'hashtag_id' => $request->hashtag_id])->delete();
            return response()->json(['msg'=>'Hashtag bookmark remove successfully', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'Hashtag bookmark data not found..!', 'status' =>'0']);
        }
    }

    // api for hashtag bookmark list
    public function get_hashtag_bookmarks(Request $request) {

        $result  = array();
        $validator = Validator::make($request->all(), [ 
            'user_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $hashtag_bookmark_data = HashtagBookmarks::leftJoin('hashtags','hashtags.id','=','hashtag_bookmarks.hashtag_id')
                            ->select("hashtag_bookmarks.*","hashtags.name as hashtag_name")
                            ->get();
        // echo "<pre>"; print_r($hashtag_bookmark_data->toArray()); die();
        if (count($hashtag_bookmark_data) > 0) 
        {  
            foreach ($hashtag_bookmark_data as $val) {
                $record_song[] = array(
                    "id"                    => $val->id,
                    "hashtag_id"            => $val->hashtag_id,
                    "hashtag_name"          => $val->hashtag_name,
                );
                $result = $record_song;
            }
            return response()->json(['data' => $result,'msg'=>'Hashtag bookmark list get successfully!', 'status' =>'1']);
        }
        return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
    }

    // api for tranding hashtag list
    // public function get_tranding_hashtag(Request $request) {

    //     $result  = array();

    //     $videos_data = Videos::leftJoin('hashtags','hashtags.id','=','hashtag_bookmarks.hashtag_id')
    //                         ->select("hashtag_bookmarks.*","hashtags.name as hashtag_name")
    //                         ->get();
    //     // echo "<pre>"; print_r($hashtag_bookmark_data->toArray()); die();
    //     if (count($hashtag_bookmark_data) > 0) 
    //     {  
    //         foreach ($hashtag_bookmark_data as $val) {
    //             $record_song[] = array(
    //                 "id"                    => $val->id,
    //                 "hashtag_id"            => $val->hashtag_id,
    //                 "hashtag_name"          => $val->hashtag_name,
    //             );
    //             $result = $record_song;
    //         }
    //         return response()->json(['data' => $result,'msg'=>'Hashtag bookmark list get successfully!', 'status' =>'1']);
    //     }
    //     return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
    // }
}
