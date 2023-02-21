<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Videos;
use App\Models\VideoData;
use App\Models\Followers;
use App\Models\User;
use App\Models\VideoComments;
use App\Models\VideoLikes;
use App\Models\VideoCommentLikes;
use App\Models\VideoReport;
use App\Models\VideoReportData;
use App\Models\VideoBookmark;
use App\Models\VideoDuets;
use App\Models\VideoFavorite;
use App\Models\VideoNotInterested;
use App\Models\VideoWatchHistory;
use App\Models\VideoCommentPinned;
use App\Models\HashtagData;
use App\Models\Notification;
use Illuminate\Http\Request;
use Validator;
use Storage;
use File;

class VideosController extends Controller
{
    //api for upload video 
    public function upload_video(Request $request)
    {
        $user_id                = $request->user_id;
        $song_id                = $request->song_id;
        $description            = $request->description;
        $is_view                = $request->is_view;
        $is_allow_comments      = $request->is_allow_comments;
        $is_allow_duet          = $request->is_allow_duet;
        $is_save_to_device      = $request->is_save_to_device;
        $friends_id             = $request->friends_id;
        $mention_ids            = $request->mention_ids;
        
        // echo "<pre>"; print_r($request->file()); die();
        
        $validator = Validator::make($request->all(), [ 
            'user_id'       => 'required',
            'is_view'       => 'required',
            'is_allow_comments' => 'required',
            'is_allow_duet'  => 'required',
            'cover_image'   => 'required',
            'video_file'    => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        if ($request->file('video_file') != '') 
        {
            
            if ($request->file('cover_image') != '') 
            {
                //cover image
                $file_cover_image = $request->file('cover_image');
                $destinationPathcover_image =  Storage::disk('public')->path('uploads/videos/cover_images');
                if(!File::exists($destinationPathcover_image)) {
                    File::makeDirectory($destinationPathcover_image,0777, true, true);
                }
                $coverimg_fileName   = time()."_".rand(11111,99999).".".$file_cover_image->getClientOriginalExtension();
                $file_cover_image->move($destinationPathcover_image, $coverimg_fileName);
            }
            else
            {
                $coverimg_fileName = "";
            }

            // video file
            $video_files = $request->file('video_file');
            $destinationPath =  Storage::disk('public')->path('uploads/videos/videos');
            if(!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath,0777, true, true);
            }
            $video_fileName   = time()."_".rand(11111,99999).".".$video_files->getClientOriginalExtension();
            $video_files->move($destinationPath, $video_fileName);

            $videos                     = new Videos();
            $videos->user_id            = $user_id;
            $videos->song_id            = isset($song_id) ? $song_id : 0;
            $videos->description        = $description;
            $videos->is_view            = $is_view;
            $videos->is_allow_comments  = $is_allow_comments;
            $videos->is_allow_duet      = $is_allow_duet;
            $videos->is_save_to_device  = $is_save_to_device?$is_save_to_device:0;
            $videos->friends_id         = $friends_id?$friends_id:'';
            $videos->mention_ids        = $mention_ids?$mention_ids:'';
            $videos->cover_image        = $coverimg_fileName?$coverimg_fileName:'';
            $videos->file_name          = $video_fileName?$video_fileName:'';
            $videos->save(); 

            $video_id = $videos->id;

            if ($request->mention_ids != '') {
            $mention_user_id = explode(",",$request->mention_ids);
            // echo "<pre>"; print_r($mention_user_id); die();
            $user_data = User::select('name')->where('id',$request->user_id)->first();
            for ($i=0; $i < count($mention_user_id); $i++) { 
                $mension_data = User::select('fcm_id')->where('id',$mention_user_id[$i])->first();
                if ($mension_data != '') {
                    if($mension_data->fcm_id<>"")
                    { 
                        $notification_id    = rand(0000,9999);
                        $find_reciever_id   = $mension_data->fcm_id;
                
                        $FCMS=array();
                        array_push($FCMS,$find_reciever_id);
                        
                        $title = $user_data->name." mention you to video";
                        $message = $user_data->name." mention you to video ".date('d-m-Y h:i A');
                        if($find_reciever_id<>"")
                        {  
                            $img = "";
                            $field = array('registration_ids'  =>array($find_reciever_id),'data'=> array( "message" => $title,"title" => $title,"body" => $message,"content"=>$message,"notification_id"=>$notification_id,"type"=>1,"id"=>$mention_user_id[$i],"image"=>$img,"sound"=>1,"vibrate"=>1));
                            
                            $fields = json_encode ($field);
                            $headers = array (
                                    'Authorization: key=AAAAzoC3TFA:APA91bHSq2d1ECf3rUcKN1pGCSj6NKOV04kgNCMac_iH04FMQ6n3iWCYbrWuKdRCL9dx7kkCpN8tDpSzoA49jSk1TuwdIEtB07ObVvHkKeQuxuAlhH3TnQfjH5-_vPqmbHmCHy5AZlvl',
                                    'Content-Type: application/json'
                            );
                            $url = 'https://fcm.googleapis.com/fcm/send';
                            $ch = curl_init ();
                            curl_setopt ( $ch, CURLOPT_URL, $url );
                            curl_setopt ( $ch, CURLOPT_POST, true );
                            curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
                            curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
                            curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
                            $resultss = curl_exec ( $ch );
                            // echo "<pre>";
                            //  print_r($resultss); die();
                            curl_close ( $ch );

                            // notificaion add
                            $notification              = new Notification();
                            $notification->user_id     = $user_id;
                            $notification->receiver_id = $mention_user_id[$i];
                            $notification->video_id    = $video_id;
                            $notification->comment     = $title;
                            $notification->type        = 4;
                            $notification->save(); 
                        }
                    }
                }
            }
        }
            if (!empty($request->hashtag_ids)) {
                $hashtag_ids = explode(",",$request->hashtag_ids);
                foreach($hashtag_ids as $k => $has) {

                    $Hashtagdata                  = new HashtagData();
                    $Hashtagdata->video_id        = $video_id;
                    $Hashtagdata->hashtag_id      = $has;
                    $Hashtagdata->save();
                }
            }

            return response()->json(['msg'=>'Video upload successfully!', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'Plase upload video file!', 'status' =>'0']);
        }
    }

    // api for delete single video
    public function delete_single_video(Request $request) {
        $validator = Validator::make($request->all(), [ 
            'video_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $videos_data = Videos::where('id',$request->video_id)->first();
        if (!empty($videos_data)) 
        {   
            $video_data_details= VideoData::where('video_id',$request->video_id)->get();
            if(!empty($video_data_details)) {
                foreach ($video_data_details as $dett) {
                    // cover_image
                    if ($dett->cover_image != '') 
                    {
                        $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                        if(!empty($dett) && !empty($dett->cover_image)){
                            if(File::exists($deldestinationPath.'/'.$dett->cover_image)) {
                                $delete=File::delete($deldestinationPath.'/'.$dett->cover_image);
                            }
                        }
                    }
                    // delete video
                    if ($dett->file_name != '') 
                    {
                       $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                        if(!empty($dett) && !empty($dett->file_name)){
                            if(File::exists($delddestinationPath.'/'.$dett->file_name)) {
                                $delete=File::delete($delddestinationPath.'/'.$dett->file_name);
                            }
                        }
                    }
                    VideoData::where('id',$dett->id)->delete();
                }
            }
            Videos::where('id',$request->video_id)->delete();
            return response()->json(['msg'=>'Video delete successfully.', 'status' =>'1']);
        }
        else
        { 
            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        }
    }

    // api for video list
    public function video_list(Request $request) {

        // $total_likes = 0;
        // $total_comments = 0;
        $validator = Validator::make($request->all(), [ 
            // 'user_id'   => 'required',
            'type'      => 'required' //1 = Following || 2 = For You
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        if ($request->type == 1) 
        {   
            if ($request->user_id != '') {
                $total_likes = 0;
                $total_comments = 0;
                $followers_data = Followers::where('user_id',$request->user_id)->get();
                // echo "<pre>"; print_r($followers_data->toArray()); die();
                if (count($followers_data) > 0) 
                {   
                    foreach ($followers_data as $val) {
                        $video_data = Videos::select('videos.*','songs.name as song_name')
                                ->leftJoin("songs","songs.id","=","videos.song_id")
                                ->where('videos.user_id','=',$val->follower_id)
                                ->where('videos.is_view',1)
                                ->where('videos.is_save_to_device',0)
                                ->get();
                        // echo "<pre>"; print_r($video_data->toArray());
                        if (count($video_data) > 0) 
                        {  
                            foreach ($video_data as $row) {
                                $VideoNotInterested = VideoNotInterested::where('user_id',$request->user_id)->where('video_id',$row->id)->count();
                                if ($VideoNotInterested == 0 || $VideoNotInterested == '0') {
                                    // user details
                                    $user_details    = User::where('id',$row->user_id)->first();
                                    if ($user_details != '') 
                                    {
                                        $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                                        if(!empty($user_details->profile_image)){
                                            if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                                                $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                                            }
                                            else
                                            {
                                                $profile_image = "";
                                            }
                                        }
                                        else
                                        {
                                            $profile_image = "";
                                        }

                                        $username = $user_details->name;
                                        $user_username = $user_details->username;
                                    }
                                    else
                                    {
                                        $username = "";
                                        $user_username = "";
                                        $profile_image = "";
                                    }

                                    // total likes
                                    $total_likes += VideoLikes::where('video_id','=',$row->id)->count();
                                    $total_this_likes = VideoLikes::where('video_id','=',$row->id)->count();
                                    // total comments
                                    $total_comments += VideoComments::where('video_id','=',$row->id)->count();
                                    $total_this_comments = VideoComments::where('video_id','=',$row->id)->count();
                                    // total watch
                                    $total_views = VideoWatchHistory::where('video_id',$row->id)->count();
                                    // cover image
                                    if ($row->cover_image != '') 
                                    {
                                        $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                                        if(File::exists($deldestinationPath.'/'.$row->cover_image)) {
                                            $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$row->cover_image);
                                        }
                                        else
                                        {
                                            $cover_image = "";
                                        }
                                    }
                                    else
                                    {
                                        $cover_image = "";
                                    }
                                    // video file
                                    if ($row->file_name != '') 
                                    {
                                       $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                                        if(File::exists($delddestinationPath.'/'.$row->file_name)) {
                                            $video_url = url('storage/app/public/uploads/videos/videos/'.$row->file_name);
                                        }
                                        else
                                        {
                                            $video_url = "";
                                        }
                                    }
                                    else
                                    {
                                        $video_url = "";
                                    }

                                    // video like or not
                                    $user_like_data= VideoLikes::where(['user_id' => $request->user_id,'video_id' => $row->id])->first();
                                    if (!empty($user_like_data)) 
                                    {
                                        $is_video_like = 1;
                                    }
                                    else
                                    {
                                        $is_video_like = 0;
                                    }

                                    // user is flow this user or not check 
                                    $is_follow_data = Followers::where(['user_id' => $request->user_id,'follower_id' => $row->user_id])->first();
                                    if (!empty($is_follow_data)) 
                                    {
                                        $is_follow = 1;
                                    }
                                    else
                                    {
                                        $is_follow = 0;
                                    }

                                    // user is bookmark or not check 
                                    $video_bookmark_data = VideoBookmark::where(['user_id' => $request->user_id,'video_id' => $row->id])->count();
                                    if ($video_bookmark_data > 0) 
                                    {
                                        $is_bookmark = 1;
                                    }
                                    else
                                    {
                                        $is_bookmark = 0;
                                    }

                                    // user is favorites or not check 
                                    $video_favorites_data = VideoFavorite::where(['user_id' => $request->user_id,'video_id' => $row->id])->count();
                                    if ($video_favorites_data > 0) 
                                    {
                                        $is_favorite = 1;
                                    }
                                    else
                                    {
                                        $is_favorite = 0;
                                    }
                                    
                                    $record[] = array(
                                        "video_id"              => $row->id,
                                        "user_id"               => (int)$row->user_id,
                                        "name"                  => $username,
                                        "username"              => $user_username,
                                        "profile_image"         => $profile_image,
                                        "song_name"             => isset($row->song_name) ? $row->song_name : '',
                                        "description"           => $row->description,
                                        "is_follow"             => $is_follow,
                                        "is_bookmark"           => $is_bookmark,
                                        "is_favorite"           => $is_favorite,
                                        "total_likes"           => $total_this_likes,
                                        "total_comments"        => $total_this_comments,
                                        "total_views"           => (int)$total_views,
                                        "is_allow_comments"     => $row->is_allow_comments,
                                        "is_allow_duet"         => $row->is_allow_duet,
                                        "is_video_like"         => $is_video_like,
                                        "cover_image"           => $cover_image,
                                        "video_url"             => $video_url,
                                    );
                                    $result = $record;
                                }
                            }
                            return response()->json(['data' => $result,'msg'=>'Video get successfully.', 'status' =>'1']);
                        }
                        else
                        {
                            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
                        }
                    }
                }
                else
                { 
                    return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
                } 
            }
            else
            { 
                return response()->json(['msg'=>'Plase provide user login id.!', 'status' =>'0']);
            } 
        }else if ($request->type == 2 && $request->user_id != '') {
            $total_likes = 0;
            $total_comments = 0;
            // $video_data = Videos::select('videos.*','songs.name as song_name')->leftJoin("songs","songs.id","=","videos.song_id")->where(['videos.is_view' => 1,'videos.is_save_to_device' => 0])->inRandomOrder()->get();

            $video_data = Videos::select('videos.*','songs.name as song_name');
            $video_data = $video_data->leftJoin("songs","songs.id","=","videos.song_id");
            $video_data = $video_data->leftJoin("video_favorites","video_favorites.video_id","=","videos.id");
            $video_data = $video_data->leftJoin("video_likes","video_likes.video_id","=","videos.id");
            $video_data = $video_data->leftJoin("followers","followers.user_id","=","videos.user_id");
            $video_data = $video_data->leftJoin("video_comments","video_comments.video_id","=","videos.id");
            $video_data = $video_data->where(['videos.is_view' => 1,'videos.is_save_to_device' => 0]);
            $video_data = $video_data->groupBy("videos.id");
            $video_data = $video_data->inRandomOrder();
            $video_data = $video_data->get();
            // echo "<pre>"; print_r($video_data->toArray()); die();
            if (count($video_data) > 0) 
            {  
                foreach ($video_data as $row) {
                    $VideoNotInterested = VideoNotInterested::where('user_id',$request->user_id)->where('video_id',$row->id)->count();
                    if ($VideoNotInterested == 0 || $VideoNotInterested == '0') {
                        // total likes
                        $total_likes += VideoLikes::where('video_id','=',$row->id)->count();
                        $total_this_likes = VideoLikes::where('video_id','=',$row->id)->count();
                        // total comments
                        $total_comments += VideoComments::where('video_id','=',$row->id)->count();
                        $total_this_comments = VideoComments::where('video_id','=',$row->id)->count();
                        // total views
                        $total_views = VideoWatchHistory::where('video_id',$row->id)->count();                            

                        if ($row->cover_image != '') 
                        {
                            $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                            if(File::exists($deldestinationPath.'/'.$row->cover_image)) {
                                $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$row->cover_image);
                            }
                            else
                            {
                                $cover_image = "";
                            }
                        }
                        else
                        {
                            $cover_image = "";
                        }
                        // delete video
                        if ($row->file_name != '') 
                        {
                           $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                            if(File::exists($delddestinationPath.'/'.$row->file_name)) {
                                $video_url = url('storage/app/public/uploads/videos/videos/'.$row->file_name);
                            }
                            else
                            {
                                $video_url = "";
                            }
                        }
                        else
                        {
                            $video_url = "";
                        }

                        // user details
                        $user_details    = User::where('id',$row->user_id)->first();
                        if ($user_details != '') 
                        {
                            $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                            if(!empty($user_details->profile_image)){
                                if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                                    $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                                }
                                else
                                {
                                    $profile_image = "";
                                }
                            }
                            else
                            {
                                $profile_image = "";
                            }

                            $username = $user_details->name;
                            $user_username = $user_details->username;
                        }
                        else
                        {
                            $username = "";
                            $user_username = "";
                            $profile_image = "";
                        }
                       
                        
                        // user is flow this user or not check 
                        $is_follow_data = Followers::where(['user_id' => $request->user_id,'follower_id' => $row->user_id])->first();
                        if (!empty($is_follow_data)) 
                        {
                            $is_follow = 1;
                        }
                        else
                        {
                            $is_follow = 0;
                        }

                        // video like or not
                        $user_like_data = VideoLikes::where(['user_id' => $request->user_id,'video_id' => $row->id])->first();
                        if (!empty($user_like_data)) 
                        {
                            $is_video_like = 1;
                        }
                        else
                        {
                            $is_video_like = 0;
                        }

                        // user is bookmark or not check 
                        $video_bookmark_data = VideoBookmark::where('user_id','=',$request->user_id)->where('video_id','=',$row->id)->count();
                        if ($video_bookmark_data == 1) 
                        {
                            $is_bookmark = 1;
                        }
                        else
                        {
                            $is_bookmark = 0;
                        }

                        // user is favorites or not check 
                        $video_favorites_data = VideoFavorite::where('user_id','=',$request->user_id)->where('video_id','=',$row->id)->count();
                        if ($video_favorites_data == 1) 
                        {
                            $is_favorite = 1;
                        }
                        else
                        {
                            $is_favorite = 0;
                        }
                        
                        $records[] = array(
                            "video_id"              => $row->id,
                            "user_id"               => (int)$row->user_id,
                            "name"                  => $username,
                            "username"              => $user_username,
                            "profile_image"         => $profile_image,
                            "song_name"             => isset($row->song_name) ? $row->song_name : '',
                            "is_allow_comments"     => $row->is_allow_comments,
                            "is_allow_duet"         => $row->is_allow_duet,
                            "total_likes"           => $total_likes,
                            "is_bookmark"           => $is_bookmark,
                            "is_favorite"           => $is_favorite,
                            "total_comments"        => $total_comments,
                            "description"           => $row->description,
                            "is_follow"             => $is_follow,
                            "is_video_like"         => $is_video_like,
                            "total_views"           => (int)$total_views,
                            "cover_image"           => $cover_image,
                            "video_url"             => $video_url,
                        );
                        $results = $records;
                        $total_likes = 0;
                        $total_comments = 0;
                    }
                }
                return response()->json(['data' => $results, 'msg'=>'Video get successfully.', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
            } 
        }
        else
        {   
            $total_likes = 0;
            $total_comments = 0;
            // $video_data = Videos::select('videos.*','songs.name as song_name')->leftJoin("songs","songs.id","=","videos.song_id")->where(['videos.is_view' => 1,'videos.is_save_to_device' => 0])->inRandomOrder()->get();

            $video_data = Videos::select('videos.*','songs.name as song_name');
            $video_data = $video_data->leftJoin("songs","songs.id","=","videos.song_id");
            // $video_data = $video_data->leftJoin("video_favorites","video_favorites.video_id","=","videos.id");
            // $video_data = $video_data->leftJoin("video_likes","video_likes.video_id","=","videos.id");
            // $video_data = $video_data->leftJoin("followers","followers.user_id","=","videos.user_id");
            // $video_data = $video_data->leftJoin("video_comments","video_comments.video_id","=","videos.id");
            $video_data = $video_data->where(['videos.is_view' => 1,'videos.is_save_to_device' => 0]);
            // $video_data = $video_data->groupBy("videos.id");
            $video_data = $video_data->inRandomOrder();
            $video_data = $video_data->get();
            // echo "<pre>"; print_r($video_data->toArray()); die();
            if (count($video_data) > 0) 
            {  
                foreach ($video_data as $row) {
                    $VideoNotInterested = VideoNotInterested::where('user_id',$request->user_id)->where('video_id',$row->id)->count();
                    if ($VideoNotInterested == 0 || $VideoNotInterested == '0') {
                        // total likes
                        $total_likes += VideoLikes::where('video_id','=',$row->id)->count();
                        $total_this_likes = VideoLikes::where('video_id','=',$row->id)->count();
                        // total comments
                        $total_comments += VideoComments::where('video_id','=',$row->id)->count();
                        $total_this_comments = VideoComments::where('video_id','=',$row->id)->count();
                        // total views
                        $total_views = VideoWatchHistory::where('video_id',$row->id)->count();                            

                        if ($row->cover_image != '') 
                        {
                            $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                            if(File::exists($deldestinationPath.'/'.$row->cover_image)) {
                                $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$row->cover_image);
                            }
                            else
                            {
                                $cover_image = "";
                            }
                        }
                        else
                        {
                            $cover_image = "";
                        }
                        // delete video
                        if ($row->file_name != '') 
                        {
                           $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                            if(File::exists($delddestinationPath.'/'.$row->file_name)) {
                                $video_url = url('storage/app/public/uploads/videos/videos/'.$row->file_name);
                            }
                            else
                            {
                                $video_url = "";
                            }
                        }
                        else
                        {
                            $video_url = "";
                        }

                        // user details
                        $user_details    = User::where('id',$row->user_id)->first();
                        if ($user_details != '') 
                        {
                            $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                            if(!empty($user_details->profile_image)){
                                if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                                    $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                                }
                                else
                                {
                                    $profile_image = "";
                                }
                            }
                            else
                            {
                                $profile_image = "";
                            }

                            $username = $user_details->name;
                            $user_username = $user_details->username;
                        }
                        else
                        {
                            $username = "";
                            $user_username = "";
                            $profile_image = "";
                        }
                       
                        
                        // user is flow this user or not check 
                        $is_follow_data = Followers::where(['user_id' => $request->user_id,'follower_id' => $row->user_id])->first();
                        if (!empty($is_follow_data)) 
                        {
                            $is_follow = 1;
                        }
                        else
                        {
                            $is_follow = 0;
                        }

                        // video like or not
                        $user_like_data = VideoLikes::where(['user_id' => $request->user_id,'video_id' => $row->id])->first();
                        if (!empty($user_like_data)) 
                        {
                            $is_video_like = 1;
                        }
                        else
                        {
                            $is_video_like = 0;
                        }

                        // user is bookmark or not check 
                        $video_bookmark_data = VideoBookmark::where('user_id','=',$request->user_id)->where('video_id','=',$row->id)->count();
                        if ($video_bookmark_data == 1) 
                        {
                            $is_bookmark = 1;
                        }
                        else
                        {
                            $is_bookmark = 0;
                        }

                        // user is favorites or not check 
                        $video_favorites_data = VideoFavorite::where('user_id','=',$request->user_id)->where('video_id','=',$row->id)->count();
                        if ($video_favorites_data == 1) 
                        {
                            $is_favorite = 1;
                        }
                        else
                        {
                            $is_favorite = 0;
                        }
                        
                        $records[] = array(
                            "video_id"              => $row->id,
                            "user_id"               => (int)$row->user_id,
                            "name"                  => $username,
                            "username"              => $user_username,
                            "profile_image"         => $profile_image,
                            "song_name"             => isset($row->song_name) ? $row->song_name : '',
                            "is_allow_comments"     => $row->is_allow_comments,
                            "is_allow_duet"         => $row->is_allow_duet,
                            "total_likes"           => $total_likes,
                            "is_bookmark"           => $is_bookmark,
                            "is_favorite"           => $is_favorite,
                            "total_comments"        => $total_comments,
                            "description"           => $row->description,
                            "is_follow"             => $is_follow,
                            "is_video_like"         => $is_video_like,
                            "total_views"           => (int)$total_views,
                            "cover_image"           => $cover_image,
                            "video_url"             => $video_url,
                        );
                        $results = $records;
                        $total_likes = 0;
                        $total_comments = 0;
                    }
                }
                return response()->json(['data' => $results, 'msg'=>'Video get successfully.', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
            } 
        }
    }

    // api for video list
    public function video_details(Request $request) {

        // $total_likes = 0;
        // $total_comments = 0;
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'  => 'required'
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $video_data = Videos::select('videos.*','songs.name as song_name')->leftJoin("songs","songs.id","=","videos.song_id")->where(['videos.id' => $request->video_id,'videos.is_view' => 1,'videos.is_save_to_device' => 0])->first();
        if ($video_data != '') 
        {  
            // user details
            $user_details    = User::where('id',$video_data->user_id)->first();
            if ($user_details != '') 
            {
                $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                if(!empty($user_details->profile_image)){
                    if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                        $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                    }
                    else
                    {
                        $profile_image = "";
                    }
                }
                else
                {
                    $profile_image = "";
                }

                $username = $user_details->name;
                $user_username = $user_details->username;
            }
            else
            {
                $username = "";
                $user_username = "";
                $profile_image = "";
            }

            // total likes
            $total_likes = VideoLikes::where('video_id','=',$video_data->id)->count();
            $total_this_likes = VideoLikes::where('video_id','=',$video_data->id)->count();
            // total comments
            $total_comments = VideoComments::where('video_id','=',$video_data->id)->count();
            $total_this_comments = VideoComments::where('video_id','=',$video_data->id)->count();
            // total watch
            $total_views = VideoWatchHistory::where('video_id',$video_data->id)->count();
            // cover image
            if ($video_data->cover_image != '') 
            {
                $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                if(File::exists($deldestinationPath.'/'.$video_data->cover_image)) {
                    $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$video_data->cover_image);
                }
                else
                {
                    $cover_image = "";
                }
            }
            else
            {
                $cover_image = "";
            }
            // video file
            if ($video_data->file_name != '') 
            {
               $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                if(File::exists($delddestinationPath.'/'.$video_data->file_name)) {
                    $video_url = url('storage/app/public/uploads/videos/videos/'.$video_data->file_name);
                }
                else
                {
                    $video_url = "";
                }
            }
            else
            {
                $video_url = "";
            }

            // video like or not
            $user_like_data= VideoLikes::where(['user_id' => $request->user_id,'video_id' => $video_data->id])->first();
            if (!empty($user_like_data)) 
            {
                $is_video_like = 1;
            }
            else
            {
                $is_video_like = 0;
            }

            // user is flow this user or not check 
            $is_follow_data = Followers::where(['user_id' => $request->user_id,'follower_id' => $video_data->user_id])->first();
            if (!empty($is_follow_data)) 
            {
                $is_follow = 1;
            }
            else
            {
                $is_follow = 0;
            }

            // user is bookmark or not check 
            $video_bookmark_data = VideoBookmark::where(['user_id' => $request->user_id,'video_id' => $video_data->id])->count();
            if ($video_bookmark_data > 0) 
            {
                $is_bookmark = 1;
            }
            else
            {
                $is_bookmark = 0;
            }

            // user is favorites or not check 
            $video_favorites_data = VideoFavorite::where(['user_id' => $request->user_id,'video_id' => $video_data->id])->count();
            if ($video_favorites_data > 0) 
            {
                $is_favorite = 1;
            }
            else
            {
                $is_favorite = 0;
            }
            
            $record[] = array(
                "video_id"              => $video_data->id,
                "user_id"               => $video_data->user_id,
                "name"                  => $username,
                "username"              => $user_username,
                "profile_image"         => $profile_image,
                "song_name"             => isset($video_data->song_name) ? $video_data->song_name : '',
                "description"           => $video_data->description,
                "is_follow"             => $is_follow,
                "is_bookmark"           => $is_bookmark,
                "is_favorite"           => $is_favorite,
                "total_likes"           => $total_this_likes,
                "total_comments"        => $total_this_comments,
                "total_views"           => (int)$total_views,
                "is_allow_comments"     => $video_data->is_allow_comments,
                "is_allow_duet"         => $video_data->is_allow_duet,
                "is_video_like"         => $is_video_like,
                "cover_image"           => $cover_image,
                "video_url"             => $video_url,
            );
            $result = $record;
            return response()->json(['data' => $result,'msg'=>'Video details get successfully.', 'status' =>'1']);
        }
        
        return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        
    }

    // api for add video like
    public function add_video_like(Request $request)
    {
        $user_id        = $request->user_id;
        $video_id       = $request->video_id;
        
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $user_id)->first();
        if (!empty($user_data)) 
        {   
            $like_data= VideoLikes::where(['user_id' => $user_id,'video_id' => $video_id])->first();
            if (!empty($like_data)) 
            {
                return response()->json(['msg'=>'This video already likeed', 'status' =>'0']);
            }
            else
            {
                $video_data= Videos::where('id',$video_id)->first();
                if (!empty($video_data)) 
                {
                    // notification send video honor
                    if ($video_data->user_id != '' && $video_data->user_id != $user_id) {
                        $video_user_data = User::where('id', $video_data->user_id)->first();
                        if($video_user_data->fcm_id<>"")
                        { 
                            $notification_id    = rand(0000,9999);
                            $find_reciever_id   = $video_user_data->fcm_id;
                    
                            $FCMS=array();
                            array_push($FCMS,$find_reciever_id);
                            
                            $title = $user_data->name." like your video";
                            $message = $user_data->name." like your video at ".date('d-m-Y h:i A');
                            if($find_reciever_id<>"")
                            {  
                                $img = "";
                                $field = array('registration_ids'  =>array($find_reciever_id),'data'=> array( "message" => $title,"title" => $title,"body" => $message,"content"=>$message,"notification_id"=>$notification_id,"type"=>1,"id"=>$video_data->user_id,"image"=>$img,"sound"=>1,"vibrate"=>1));
                                
                                $fields = json_encode ($field);
                                $headers = array (
                                        'Authorization: key=AAAAzoC3TFA:APA91bHSq2d1ECf3rUcKN1pGCSj6NKOV04kgNCMac_iH04FMQ6n3iWCYbrWuKdRCL9dx7kkCpN8tDpSzoA49jSk1TuwdIEtB07ObVvHkKeQuxuAlhH3TnQfjH5-_vPqmbHmCHy5AZlvl',
                                        'Content-Type: application/json'
                                );
                                $url = 'https://fcm.googleapis.com/fcm/send';
                                $ch = curl_init ();
                                curl_setopt ( $ch, CURLOPT_URL, $url );
                                curl_setopt ( $ch, CURLOPT_POST, true );
                                curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
                                curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
                                curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
                                $resultss = curl_exec ( $ch );
                                // echo "<pre>";
                                //  print_r($resultss); die();
                                curl_close ( $ch );

                                // notificaion add
                                $notification              = new Notification();
                                $notification->user_id     = $user_id;
                                $notification->receiver_id = $video_data->user_id;
                                $notification->video_id    = $video_id;
                                $notification->comment     = $title;
                                $notification->type        = 1;
                                $notification->save(); 
                            }
                        }
                    }
                    $videolikes              = new VideoLikes();
                    $videolikes->user_id     = $user_id;
                    $videolikes->video_id    = $video_id;
                    $videolikes->save(); 


                    return response()->json(['msg'=>'Video like successfully!.', 'status' =>'1']);
                }
                else
                {
                    return response()->json(['msg'=>'This Video not found our database!.', 'status' =>'0']);
                }
            }
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for remove video like
    public function remove_video_like(Request $request)
    {
        $user_id        = $request->user_id;
        $video_id       = $request->video_id;
        
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $user_id)->first();
        if (!empty($user_data)) 
        {   
            $like_data= VideoLikes::where(['user_id' => $user_id,'video_id' => $video_id])->first();
            if (!empty($like_data)) 
            {
                VideoLikes::where(['user_id' => $user_id,'video_id' => $video_id])->delete();
                return response()->json(['msg'=>'Video like remove successfully', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'This video not found likes our database!.', 'status' =>'0']);
            }
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for video list
    public function get_video_likes(Request $request) {
        $validator = Validator::make($request->all(), [ 
            'user_id'  => 'required',
            'video_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $video_likes_data = VideoLikes::where('video_id',$request->video_id)->get();
        if (count($video_likes_data) > 0) 
        {  
            foreach ($video_likes_data as $row) {
                $user_details    = User::where('id',$row->user_id)->first();
                if ($user_details != '') 
                {
                    $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                    if(!empty($user_details->profile_image)){
                        if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                            $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                        }
                        else
                        {
                            $profile_image = "";
                        }
                    }
                    else
                    {
                        $profile_image = "";
                    }

                    $username = $user_details->name;
                    $user_username = $user_details->username;
                }
                else
                {
                    $username = "";
                    $user_username = "";
                    $profile_image = "";
                }

                if ($request->user_id == $row->user_id) 
                {
                    $is_like = 1;
                }
                else
                {
                    $is_like = 0;
                }
                $record[] = array(
                    "id"                    => $row->id,
                    "name"                  => $username,
                    "username"              => $user_username,
                    "profile_image"         => $profile_image,
                    "is_like"               => $is_like,
                );
                $result = $record;
            }
            return response()->json(['data' => $result,'msg'=>'Video like get successfully.', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        } 
    }

    // api for video add comment 
    public function add_video_comments(Request $request)
    {
        $user_id        = $request->user_id;
        $video_id       = $request->video_id;
        $comment        = $request->comment;
        $mention_user   = $request->mention_user;
        
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $user_id)->first();
        if (!empty($user_data)) 
        {   
            $video_data= Videos::where('id',$video_id)->first();
            if (!empty($video_data)) 
            {
                $Videocomments              = new VideoComments();
                $Videocomments->user_id     = $user_id;
                $Videocomments->video_id    = $video_id;
                $Videocomments->mention_user = $mention_user?$mention_user:'';
                $Videocomments->comment     = $comment?$comment:'';
                $Videocomments->parent_id   = 0;
                $Videocomments->save(); 

                $comment_id = $Videocomments->id;
                 
                // notification send video honor
                if ($video_data->user_id != '') {
                    $video_user_data = User::where('id', $video_data->user_id)->first();
                    if($video_user_data->fcm_id<>"")
                    { 
                        $notification_id    = rand(0000,9999);
                        $find_reciever_id   = $video_user_data->fcm_id;
                
                        $FCMS=array();
                        array_push($FCMS,$find_reciever_id);
                        
                        $title = $user_data->name." comment your video";
                        $message = $user_data->name." comment your video at ".date('d-m-Y h:i A');
                        if($find_reciever_id<>"")
                        {  
                            $img = "";
                            $field = array('registration_ids'  =>array($find_reciever_id),'data'=> array( "message" => $title,"title" => $title,"body" => $message,"content"=>$message,"notification_id"=>$notification_id,"type"=>1,"id"=>$video_data->user_id,"image"=>$img,"sound"=>1,"vibrate"=>1));
                            
                            $fields = json_encode ($field);
                            $headers = array (
                                    'Authorization: key=AAAAzoC3TFA:APA91bHSq2d1ECf3rUcKN1pGCSj6NKOV04kgNCMac_iH04FMQ6n3iWCYbrWuKdRCL9dx7kkCpN8tDpSzoA49jSk1TuwdIEtB07ObVvHkKeQuxuAlhH3TnQfjH5-_vPqmbHmCHy5AZlvl',
                                    'Content-Type: application/json'
                            );
                            $url = 'https://fcm.googleapis.com/fcm/send';
                            $ch = curl_init ();
                            curl_setopt ( $ch, CURLOPT_URL, $url );
                            curl_setopt ( $ch, CURLOPT_POST, true );
                            curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
                            curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
                            curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
                            $resultss = curl_exec ( $ch );
                            // echo "<pre>";
                            //  print_r($resultss); die();
                            curl_close ( $ch );

                            // notificaion add
                            $notification              = new Notification();
                            $notification->user_id     = $user_id;
                            $notification->receiver_id = $video_data->user_id;
                            $notification->comment_id  = $comment_id;
                            $notification->video_id    = $video_id;
                            $notification->comment     = $comment?$comment:'';;
                            $notification->type        = 2;
                            $notification->save(); 
                        }
                    }
                }

                

                return response()->json(['msg'=>'Video comment add successfully!.', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'This Video not found our database!.', 'status' =>'0']);
            }
            
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for video add parent comment 
    public function add_parent_comment(Request $request)
    {
        $user_id        = $request->user_id;
        $video_id       = $request->video_id;
        $comment        = $request->comment;
        $comment_id     = $request->comment_id;
        $mention_user   = $request->mention_user;
        
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'   => 'required',
            'comment_id'   => 'required',
            'mention_user'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $user_id)->first();
        if (!empty($user_data)) 
        {   
            $video_data= Videos::where('id',$video_id)->first();
            if (!empty($video_data)) 
            {
                $Videocomments              = new VideoComments();
                $Videocomments->user_id     = $user_id;
                $Videocomments->video_id    = $video_id;
                $Videocomments->comment     = $comment?$comment:'';
                $Videocomments->parent_id   = $comment_id;
                $Videocomments->mention_user= $mention_user?$mention_user:'';
                $Videocomments->save(); 

                return response()->json(['msg'=>'New comment add successfully!.', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'This Video not found our database!.', 'status' =>'0']);
            }
            
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for remvoe video comments
    public function remove_video_comments(Request $request)
    {
        $user_id        = $request->user_id;
        $comment_id       = $request->comment_id;

        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'comment_id'   => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $user_id)->first();
        if (!empty($user_data)) 
        {   
            $video_comments_data = VideoComments::where(['user_id' => $user_id,'id' => $comment_id])->first();
            if (!empty($video_comments_data)) 
            {
                VideoComments::where('id',$comment_id)->delete();
                return response()->json(['msg'=>'Video comment remove successfully', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'This video comment not found..!.', 'status' =>'0']);
            }
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for video list
    public function get_video_comments(Request $request) {

        $total_comments = 0;

        $validator = Validator::make($request->all(), [ 
            'user_id'  => 'required',
            'video_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $video_indded_data = VideoCommentPinned::where('user_id',$request->user_id)->count();
        if ($video_indded_data > 0) {
            $video_comments_data = VideoComments::leftJoin("video_comment_pinneds","video_comment_pinneds.comment_id","=","video_comments.id")
                    ->select("video_comments.*")
                    ->where(['video_comments.video_id' => $request->video_id,'video_comments.parent_id' => "'0'"])
                    ->groupBy("video_comments.id")
                    ->orderBy("video_comment_pinneds.comment_id","DESC")
                    ->get();
        }else{
            $video_comments_data = VideoComments::where(['video_id' => $request->video_id,'parent_id' => "'0'"])
                    ->orderBy("id","DESC")
                    ->get();
        }
        if (count($video_comments_data) > 0) 
        {  
            $video_data = Videos::where('videos.id',$request->video_id)
                        ->select('videos.user_id as video_user_id')
                        ->first();
            $is_video_creator = isset($video_data->video_user_id) ? $video_data->video_user_id : 0;          
            // echo "<pre>"; print_r($video_data->toArray()); die();
            $total_comments = VideoComments::where(['video_id' => $request->video_id,'parent_id' => "'0'"])->count();
            foreach ($video_comments_data as $row) {

                $total_comment_likes = VideoCommentLikes::where('video_id',$request->video_id)->where('comment_id',$row->id)->orWhere('comment_id',$row->parent_id ? $row->parent_id : 0)->count();
                $video_parent_comments_data = VideoComments::where('parent_id',$row->id)->get();
                if (count($video_parent_comments_data) > 0) 
                {  
                    // $total_parent_comments = VideoComments::where('parent_id',"=",$row->id)->count();
                    foreach ($video_parent_comments_data as $par) {
                        $user_details    = User::where('id',$par->user_id)->first();
                        if ($user_details != '') 
                        {
                            $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                            if(!empty($user_details->profile_image)){
                                if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                                    $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                                }
                                else
                                {
                                    $profile_image = "";
                                }
                            }
                            else
                            {
                                $profile_image = "";
                            }

                            $username = $user_details->name;
                            $user_username = $user_details->username;
                        }
                        else
                        {
                            $username = "";
                            $user_username = "";
                            $profile_image = "";
                        }

                        if ($is_video_creator == $par->user_id) 
                        {
                            $is_creator = 1;
                        }
                        else
                        {
                            $is_creator = 0;
                        }

                        $comment_like_dataa = VideoCommentLikes::where(['video_id' => $request->video_id,'user_id' => $request->user_id,'comment_id' => $par->id])->count();
                        if ($comment_like_dataa != '' || $comment_like_dataa == 1 || $comment_like_dataa > 0) 
                        {
                            $is_like_commentt = 1;
                        }
                        else
                        {
                            $is_like_commentt = 0;
                        }

                        $comment_like_data = VideoCommentPinned::where(['user_id' => $request->user_id,'comment_id' => $par->id])->count();
                        if ($comment_like_data != '' || $comment_like_data == 1 || $comment_like_data > 0) 
                        {
                            $is_pinned_comment = 1;
                        }
                        else
                        {
                            $is_pinned_comment = 0;
                        }
                        $parent_record[] = array(
                            "id"                    => $par->id,
                            "video_id"              => $par->video_id,
                            "name"                  => $username,
                            "username"              => $user_username,
                            "profile_image"         => $profile_image,
                            "comment"               => $par->comment,
                            "mention_user"          => isset($par->mention_user) ? $par->mention_user : '',
                            "is_creator"            => $is_creator,
                            "is_pinned_comment"     => $is_pinned_comment,
                            "is_like_comment"       => $is_like_commentt,
                            "created_at"            => date("Y-m-d H:i:s",strtotime($par->created_at)),
                        );
                        $parent_comments = $parent_record;
                    }
                }
                else
                {
                    $parent_comments = array();
                }

                $user_details    = User::where('id',$row->user_id)->first();
                if ($user_details != '') 
                {
                    $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                    if(!empty($user_details->profile_image)){
                        if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                            $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                        }
                        else
                        {
                            $profile_image = "";
                        }
                    }
                    else
                    {
                        $profile_image = "";
                    }

                    $user_id = $user_details->id;
                    $username = $user_details->name;
                    $user_username = $user_details->username;
                }
                else
                {
                    $user_id = "";
                    $username = "";
                    $user_username = "";
                    $profile_image = "";
                }

                if ($is_video_creator == $row->user_id) 
                {
                    $is_creator = 1;
                }
                else
                {
                    $is_creator = 0;
                }

                $comment_like_data = VideoCommentLikes::where(['video_id' => $request->video_id,'user_id' => $request->user_id,'comment_id' => $row->id])->count();
                if ($comment_like_data != '' && $comment_like_data > 0) 
                {
                    $is_like_comment = 1;
                }
                else
                {
                    $is_like_comment = 0;
                }

                $comment_like_data = VideoCommentPinned::where(['user_id' => $request->user_id,'comment_id' => $row->id])->count();
                if ($comment_like_data != '' || $comment_like_data == 1 || $comment_like_data > 0) 
                {
                    $is_pinned_comment = 1;
                }
                else
                {
                    $is_pinned_comment = 0;
                }
                $record[] = array(
                    "id"                    => $row->id,
                    "video_id"              => $row->video_id,
                    "user_id"               => $user_id,
                    "name"                  => $username,
                    "username"              => $user_username,
                    "profile_image"         => $profile_image,
                    "comment"               => $row->comment,
                    "mention_user"          => isset($row->mention_user) ? $row->mention_user : '',
                    "is_creator"            => $is_creator,
                    "is_like_comment"       => $is_like_comment,
                    "is_pinned_comment"     => $is_pinned_comment,
                    "total_comment_likes"   => $total_comment_likes,
                    "total_parent_comments" => count($parent_comments),
                    "created_at"            => date("Y-m-d H:i:s",strtotime($row->created_at)),
                    "parent_comments"       => $parent_comments,

                );
                $result = $record;
                unset($parent_record);
                $total_comment_likes = 0;
            }
            return response()->json(['data' => $result,'total_comments' => $total_comments,'msg'=>'Video comments get successfully.', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        } 
    }

    // api for parent video list
    public function get_parent_video_comments(Request $request) {

        $total_comments = 0;

        $validator = Validator::make($request->all(), [ 
            'user_id'  => 'required',
            'video_id'  => 'required',
            'comment_id'  => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $video_comments_data = VideoComments::where(['video_id' => $request->video_id,'parent_id' => $request->comment_id])->get();
        if (count($video_comments_data) > 0) 
        {  
            $video_data = Videos::where('id',$request->video_id)->first();
            $is_video_creator = isset($video_data->user_id) ? $video_data->user_id : 0;          
            // echo "<pre>"; print_r($video_data->toArray()); die();
            $total_comments = VideoComments::where(['video_id' => $request->video_id,'parent_id' => $request->comment_id])->count();
            foreach ($video_comments_data as $row) {

                $total_comment_likes = VideoCommentLikes::where('video_id',$request->video_id)->where('comment_id',$row->id)->orWhere('comment_id',$row->parent_id ? $row->parent_id : 0)->count();
                $user_details    = User::where('id',$row->user_id)->first();
                if ($user_details != '') 
                {
                    $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                    if(!empty($user_details->profile_image)){
                        if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                            $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                        }
                        else
                        {
                            $profile_image = "";
                        }
                    }
                    else
                    {
                        $profile_image = "";
                    }

                    $user_id = $user_details->id;
                    $username = $user_details->name;
                    $user_username = $user_details->username;
                }
                else
                {
                    $user_id = "";
                    $username = "";
                    $user_username = "";
                    $profile_image = "";
                }

                if ($is_video_creator == $row->user_id) 
                {
                    $is_creator = 1;
                }
                else
                {
                    $is_creator = 0;
                }

                $comment_like_data = VideoCommentLikes::where(['video_id' => $row->video_id,'user_id' => $request->user_id,'comment_id' => $row->id])->count();
                if ($comment_like_data != '' || $comment_like_data == 1 || $comment_like_data > 0) 
                {
                    $is_like_comment = 1;
                }
                else
                {
                    $is_like_comment = 0;
                }
                $record[] = array(
                    "id"                    => $row->id,
                    "comment_id"            => $request->comment_id,
                    "video_id"              => $row->video_id,
                    "user_id"               => $user_id,
                    "name"                  => $username,
                    "username"              => $user_username,
                    "profile_image"         => $profile_image,
                    "comment"               => $row->comment,
                    "mention_user"          => isset($row->mention_user) ? $row->mention_user : '',
                    "is_creator"            => $is_creator,
                    "is_like_comment"       => $is_like_comment,
                    "total_comment_likes"   => $total_comment_likes,
                    "created_at"            => date("Y-m-d H:i:s",strtotime($row->created_at)),

                );
                $result = $record;
                unset($parent_record);
                $total_comment_likes = 0;
            }
            return response()->json(['data' => $result,'total_comments' => $total_comments,'msg'=>'Video comments get successfully.', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        } 
    }

    // get Private video by position
    public function private_position_video_list(Request $request) {
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'  => 'required',
            'login_id'  => 'required'
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $video_data = Videos::where(['user_id' => $request->user_id,'is_view' => 3,'is_save_to_device' => 0])->where("id","!=",$request->video_id)->orderBy('id', 'DESC')->get();
        if (count($video_data) > 0) 
        {  
            foreach ($video_data as $row) {

                $user_details    = User::where('id',$row->user_id)->first();
                if ($user_details != '') 
                {
                    $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                    if(!empty($user_details->profile_image)){
                        if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                            $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                        }
                        else
                        {
                            $profile_image = "";
                        }
                    }
                    else
                    {
                        $profile_image = "";
                    }

                    $username = $user_details->name;
                    $user_username = $user_details->username;
                }
                else
                {
                    $username = "";
                    $user_username = "";
                    $profile_image = "";
                }


                // total likes
                $total_likes = VideoLikes::where('video_id',$row->id)->count();
                // total comments
                $total_comments = VideoComments::where('video_id',$row->id)->where('parent_id',0)->count();

                // video like or not
                $user_like_data= VideoLikes::where(['user_id' => $request->login_id,'video_id' => $row->id])->first();
                if (!empty($user_like_data)) 
                {
                    $is_video_like = 1;
                }
                else
                {
                    $is_video_like = 0;
                }

                if ($row->cover_image != '') 
                {
                    $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                    if(File::exists($deldestinationPath.'/'.$row->cover_image)) {
                        $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$row->cover_image);
                    }
                    else
                    {
                        $cover_image = "";
                    }
                }
                else
                {
                    $cover_image = "";
                }
                // delete video
                if ($row->file_name != '') 
                {
                   $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                    if(File::exists($delddestinationPath.'/'.$row->file_name)) {
                        $video_url = url('storage/app/public/uploads/videos/videos/'.$row->file_name);
                    }
                    else
                    {
                        $video_url = "";
                    }
                }
                else
                {
                    $video_url = "";
                }

                $record_video_file[] = array(
                    "video_id"              => $row->id,
                    "user_id"               => $row->user_id,
                    "name"                  => $username,
                    "username"              => $user_username,
                    "profile_image"         => $profile_image,
                    "description"           => $row->description,
                    "is_allow_comments"     => $row->is_allow_comments,
                    "is_allow_duet"         => $row->is_allow_duet,
                    "is_video_like"         => $is_video_like,
                    "total_likes"           => $total_likes,
                    "total_comments"        => $total_comments,
                    "cover_image"           => $cover_image,
                    "video_url"             => $video_url,
                );
                $result = $record_video_file;
            }
        }
        else
        {
            $result = array();
        }

        // first video get data
        $video_details    = Videos::where('id',$request->video_id)->first();
        $user_details    = User::where('id',$video_details->user_id)->first();
        if ($user_details != '') 
        {
            $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
            if(!empty($user_details->profile_image)){
                if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                    $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                }
                else
                {
                    $profile_image = "";
                }
            }
            else
            {
                $profile_image = "";
            }

            $username = $user_details->name;
            $user_username = $user_details->username;
        }
        else
        {
            $username = "";
            $user_username = "";
            $profile_image = "";
        }


        // total likes
        $total_likes = VideoLikes::where('video_id',$video_details->id)->count();
        // total comments
        $total_comments = VideoComments::where('video_id',$video_details->id)->where('parent_id',0)->count();

        // video like or not
        $user_like_data= VideoLikes::where(['user_id' => $request->login_id,'video_id' => $video_details->id])->first();
        if (!empty($user_like_data)) 
        {
            $is_video_like = 1;
        }
        else
        {
            $is_video_like = 0;
        }

        if ($video_details->cover_image != '') 
        {
            $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
            if(File::exists($deldestinationPath.'/'.$video_details->cover_image)) {
                $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$video_details->cover_image);
            }
            else
            {
                $cover_image = "";
            }
        }
        else
        {
            $cover_image = "";
        }
        // delete video
        if ($video_details->file_name != '') 
        {
           $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
            if(File::exists($delddestinationPath.'/'.$video_details->file_name)) {
                $video_url = url('storage/app/public/uploads/videos/videos/'.$video_details->file_name);
            }
            else
            {
                $video_url = "";
            }
        }
        else
        {
            $video_url = "";
        }

        $record_video_files[] = array(
            "video_id"              => $video_details->id,
            "user_id"               => $video_details->user_id,
            "name"                  => $username,
            "username"              => $user_username,
            "profile_image"         => $profile_image,
            "description"           => $video_details->description,
            "is_allow_comments"     => $video_details->is_allow_comments,
            "is_allow_duet"         => $video_details->is_allow_duet,
            "is_video_like"         => $is_video_like,
            "total_likes"           => $total_likes,
            "total_comments"        => $total_comments,
            "cover_image"           => $cover_image,
            "video_url"             => $video_url,
        );
        $single_result = $record_video_files;

       
        $main_array = array_merge($single_result,$result);

        if ($main_array != '') {
            return response()->json(['data' => $main_array,'msg'=>'Video get successfully.', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        }

    }

    // get video by position
    public function position_video_list(Request $request) {
        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'  => 'required',
            'login_id'  => 'required'
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }

        $video_data = Videos::where(['user_id' => $request->user_id,'is_view' => 1,'is_save_to_device' => 0])->where("id","!=",$request->video_id)->orderBy('id', 'DESC')->get();
        if (count($video_data) > 0) 
        {  
            foreach ($video_data as $row) {

                $user_details    = User::where('id',$row->user_id)->first();
                if ($user_details != '') 
                {
                    $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
                    if(!empty($user_details->profile_image)){
                        if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                            $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                        }
                        else
                        {
                            $profile_image = "";
                        }
                    }
                    else
                    {
                        $profile_image = "";
                    }

                    $username = $user_details->name;
                    $user_username = $user_details->username;
                }
                else
                {
                    $username = "";
                    $user_username = "";
                    $profile_image = "";
                }


                // total likes
                $total_likes = VideoLikes::where('video_id',$row->id)->count();
                // total comments
                $total_comments = VideoComments::where('video_id',$row->id)->where('parent_id',0)->count();

                // video like or not
                $user_like_data= VideoLikes::where(['user_id' => $request->login_id,'video_id' => $row->id])->first();
                if (!empty($user_like_data)) 
                {
                    $is_video_like = 1;
                }
                else
                {
                    $is_video_like = 0;
                }

                if ($row->cover_image != '') 
                {
                    $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                    if(File::exists($deldestinationPath.'/'.$row->cover_image)) {
                        $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$row->cover_image);
                    }
                    else
                    {
                        $cover_image = "";
                    }
                }
                else
                {
                    $cover_image = "";
                }
                // delete video
                if ($row->file_name != '') 
                {
                   $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                    if(File::exists($delddestinationPath.'/'.$row->file_name)) {
                        $video_url = url('storage/app/public/uploads/videos/videos/'.$row->file_name);
                    }
                    else
                    {
                        $video_url = "";
                    }
                }
                else
                {
                    $video_url = "";
                }

                $record_video_file[] = array(
                    "video_id"              => $row->id,
                    "user_id"               => $row->user_id,
                    "name"                  => $username,
                    "username"              => $user_username,
                    "profile_image"         => $profile_image,
                    "description"           => $row->description,
                    "is_allow_comments"     => $row->is_allow_comments,
                    "is_allow_duet"         => $row->is_allow_duet,
                    "is_video_like"         => $is_video_like,
                    "total_likes"           => $total_likes,
                    "total_comments"        => $total_comments,
                    "cover_image"           => $cover_image,
                    "video_url"             => $video_url,
                );
                $result = $record_video_file;
            }
        }
        else
        {
            $result = array();
        }

        // first video get data
        $video_details    = Videos::where('id',$request->video_id)->first();
        $user_details    = User::where('id',$video_details->user_id)->first();
        if ($user_details != '') 
        {
            $destinationPath =  Storage::disk('public')->path('uploads/user/profile');
            if(!empty($user_details->profile_image)){
                if(File::exists($destinationPath.'/'.$user_details->profile_image)) {
                    $profile_image = url('storage/app/public/uploads/user/profile/'.$user_details->profile_image);
                }
                else
                {
                    $profile_image = "";
                }
            }
            else
            {
                $profile_image = "";
            }

            $username = $user_details->name;
            $user_username = $user_details->username;
        }
        else
        {
            $username = "";
            $user_username = "";
            $profile_image = "";
        }


        // total likes
        $total_likes = VideoLikes::where('video_id',$video_details->id)->count();
        // total comments
        $total_comments = VideoComments::where('video_id',$video_details->id)->where('parent_id',0)->count();

        // video like or not
        $user_like_data= VideoLikes::where(['user_id' => $request->login_id,'video_id' => $video_details->id])->first();
        if (!empty($user_like_data)) 
        {
            $is_video_like = 1;
        }
        else
        {
            $is_video_like = 0;
        }

        if ($video_details->cover_image != '') 
        {
            $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
            if(File::exists($deldestinationPath.'/'.$video_details->cover_image)) {
                $cover_image = url('storage/app/public/uploads/videos/cover_images/'.$video_details->cover_image);
            }
            else
            {
                $cover_image = "";
            }
        }
        else
        {
            $cover_image = "";
        }
        // delete video
        if ($video_details->file_name != '') 
        {
           $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
            if(File::exists($delddestinationPath.'/'.$video_details->file_name)) {
                $video_url = url('storage/app/public/uploads/videos/videos/'.$video_details->file_name);
            }
            else
            {
                $video_url = "";
            }
        }
        else
        {
            $video_url = "";
        }

        $record_video_files[] = array(
            "video_id"              => $video_details->id,
            "user_id"               => $video_details->user_id,
            "name"                  => $username,
            "username"              => $user_username,
            "profile_image"         => $profile_image,
            "description"           => $video_details->description,
            "is_allow_comments"     => $video_details->is_allow_comments,
            "is_allow_duet"         => $video_details->is_allow_duet,
            "is_video_like"         => $is_video_like,
            "total_likes"           => $total_likes,
            "total_comments"        => $total_comments,
            "cover_image"           => $cover_image,
            "video_url"             => $video_url,
        );
        $single_result = $record_video_files;

       
        $main_array = array_merge($single_result,$result);

        if ($main_array != '') {
            return response()->json(['data' => $main_array,'msg'=>'Video get successfully.', 'status' =>'1']);
        }
        else
        {
            return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
        }

    }

    // api for update video privacy
    public function update_video_status(Request $request)
    {
        $user_id        = $request->user_id;
        $video_id       = $request->video_id;
        $status         = $request->status;

        $validator = Validator::make($request->all(), [ 
            'user_id'   => 'required',
            'video_id'  => 'required',
            'status'    => 'required',
        ]);

        if ($validator->fails())
        { 
            $message = $validator->errors()->first();
            return response()->json(['data' => [],'msg'=>$message, 'status' =>'0']);            
        }
        
        $user_data= User::where('id', $user_id)->first();
        if (!empty($user_data)) 
        {   
            $video_data = Videos::where('id',$video_id)->first();
            if (!empty($video_data)) 
            {
                $update_data['is_view'] = $status; 
                Videos::where('id',$video_id)->update($update_data);
                return response()->json(['msg'=>'Video status update successfully', 'status' =>'1']);
            }
            else
            {
                return response()->json(['msg'=>'This video not found..!.', 'status' =>'0']);
            }
        }
        else
        { 
            return response()->json(['msg'=>'This user not exist our database.!', 'status' =>'0']);
        }
    }

    // api for delete all videos
    function delete_all_videos()
    {
        $video_data= Videos::get();
        // echo "<pre>"; print_r($user_data); die();
        if(!empty($video_data)) {
            foreach ($video_data as $row) {
                $video_data_details= VideoData::where('video_id',$row->id)->get();
                if(!empty($video_data_details)) {
                    foreach ($video_data_details as $dett) {
                        // cover_image
                        if ($dett->cover_image != '') 
                        {
                           $deldestinationPath =  Storage::disk('public')->path('uploads/videos/cover_images');
                            if(!empty($dett) && !empty($dett->cover_image)){
                                if(File::exists($deldestinationPath.'/'.$dett->cover_image)) {
                                    $delete=File::delete($deldestinationPath.'/'.$dett->cover_image);
                                }
                            }
                        }
                        // delete video
                        if ($dett->file_name != '') 
                        {
                           $delddestinationPath =  Storage::disk('public')->path('uploads/videos/videos');
                            if(!empty($dett) && !empty($dett->file_name)){
                                if(File::exists($delddestinationPath.'/'.$dett->file_name)) {
                                    $delete=File::delete($delddestinationPath.'/'.$dett->file_name);
                                }
                            }
                        }
                        // bookmark video delete
                        VideoBookmark::where('video_id',$dett->id)->delete();
                        // video comments delete
                        VideoComments::where('video_id',$dett->id)->delete();
                        // video comment likes delete
                        VideoCommentLikes::where('video_id',$dett->id)->delete();
                        // video duets delete
                        VideoDuets::where('video_id',$dett->id)->delete();
                        // video favorites delete
                        VideoFavorite::where('video_id',$dett->id)->delete();
                        // video likes delete
                        VideoLikes::where('video_id',$dett->id)->delete();
                        // video not interesteds delete
                        VideoNotInterested::where('video_id',$dett->id)->delete();
                        // video reports delete
                        $video_report_data = VideoReport::where('video_id',$dett->id)->get();
                        if(!empty($video_report_data)) {
                            foreach ($video_report_data as $rep) {
                                $video_report_details_data = VideoReportData::where('report_id',$rep->id)->get();
                                if(!empty($video_report_details_data)) {
                                    foreach ($video_report_details_data as $redetail) {
                                        // cover_image
                                        if ($redetail->file_name != '') 
                                        {
                                           $deldestinationPath =  Storage::disk('public')->path('uploads/videos/reports');
                                            if(!empty($redetail) && !empty($redetail->file_name)){
                                                if(File::exists($deldestinationPath.'/'.$redetail->file_name)) {
                                                    $delete=File::delete($deldestinationPath.'/'.$redetail->file_name);
                                                }
                                            }
                                        }
                                        VideoReportData::where('id',$redetail->id)->delete();
                                    }
                                }
                            }
                            VideoReport::where('id',$rep->id)->delete();
                        }
                        // delete video file and data
                        VideoData::where('id',$dett->id)->delete();
                    }
                }
                // delete video
                Videos::where('id',$row->id)->delete();
            }
            return response()->json(['msg'=>'All video delete successfully.', 'status' =>'1']);
        }
        return response()->json(['msg'=>'No data found.!', 'status' =>'0']);
    }

    
}
