<?php

/*************************************************************
* user_module()
* This tracks all the activities related to user logins.
*************************************************************/
  class user_module
  {
    private $app;
    public function __construct($app)
    {
        $this->app = $app;
    }

 
    /*************************************************************
    * authorize()
    * This function checks for logiin existence in db.
    *
    * inputs: 
    * @username - username
    * @password - password
    * 
    * output: 
    * return Success message on success 
    * else returns reason for failure
    *************************************************************/  
    public function authorize($request,$response,$args) 
    {
        $data_array = $this->app->request->getParsedBody();  
        $result = array();     
      
        $user_name  =  $this->app->utilities->clean_string($data_array,'username');
        $password   =  $this->app->utilities->clean_string($data_array,'password');

        //inspect data array for required values.
        if( $user_name == "" ||  $password == "") 
        {

           $result = $this->app->ps_failed;
           $result['message'] = array();
           if(!isset($data_array['username']))
           {
                array_push($result['message'],'Login Id is required');
           }

           if(!isset($data_array['password'])){
                array_push($result['message'],'Password is required');
           }

           $newresponse = $response->withStatus($this->app->validation_failed_status);
           
        }
        else
        {
            //Validation Logic starts here
            $email_check = $this->app->utilities->check_email_format($data_array['username']);
            if(!$email_check)
            {   
               $result = $this->app->ps_failed;
               $result['message'] = array();
               array_push($result['message'],'Login Id is not a valid email address');
            }

            if(0)
            {
                 $newresponse = $response->withStatus($this->app->validation_failed_status);
            }
            else
            {
                // user id and password are as per definition. check authentication.
                $md5_pwd = md5($password);
                $user_details  = $this->validate_user($user_name, $md5_pwd);
                if( is_array($user_details) && count($user_details)>0 )
                {
                      $result = $this->app->ps_success;
                      $result['message'] = "Login Success.";
                      $result['data'] = array( 'email' => $user_details[0]['email_id'],'name' => $user_details[0]['name'] );
                      $newresponse = $response->withStatus($this->app->success_status);
                } 
                else
                {
                     $result = $this->app->ps_failed;
                     $result['message'] = "Invalid Login credentials.";
                     $newresponse =   $response->withStatus($this->app->invalid_login);
                }

                 
            }

            
        }        
       
        $response->getBody()->write(json_encode($result));
        return $newresponse;
    }

    /*************************************************************
    * validate_user()
    * This function Validates user and return user details if 
    * exists.
    *************************************************************/
    public function validate_user($email_id='', $password='') 
    {
        $query  = "SELECT * FROM tbl_admins WHERE email_id='$email_id' AND password='$password' ";

        $db  =  $this->app->db;
        $result = $db->query($query);
        $data = array();
        while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) 
        {
            $data[] = $row;
        }
        return $data;
    }

    /**************************************************************
    * create_update_building()
    * This function is used to create/update the project details
    *
    * inputs: 
    * @building_name - name of the building.
    * @building_code - Code Name of building
    * @address - address of building
    * @building_photo - building photo
    * 
    * output: 
    * return Success message on successful insertion
    * else returns reason on failure
    *
    ***************************************************************/
    public function create_update_building($request,$response,$args)
    {

        $data_array = $request->getParsedBody();
        $result = array();
        $errors = array();

        // Project Name Validation
        $data_array['building_name']  =  $this->app->utilities->clean_string($data_array,'building_name');
        $data_array['building_logo'] = $this->app->utilities->clean_string($data_array,'building_logo');
        $data_array['building_photo'] = $this->app->utilities->clean_string($data_array,'building_photo');
        $data_array['building_id'] = $this->app->utilities->clean_string($args,'id');
        $data_array['location_name'] = $this->app->utilities->clean_string($data_array,'location_name');
        //$data_array['address'] =  $this->app->utilities->clean_string($data_array,'address');

        if($data_array['building_name'] == '')
        {
            array_push($errors,"Building Name is required");
        }

        if($data_array['building_logo'] == '')
        {
            array_push($errors,"Building Logo is required");
        }

        if($data_array['building_photo'] == '')
        {
            array_push($errors,"Building Image is required");
        }

        if($data_array['building_id'] != "")
        {
            $temp_data = array();
            $temp_data = $this->app->utilities->is_record_exist('tbl_buildings','building_id',$data_array['building_id']," and delete_status=0");

            if(count($temp_data) == 0)
            {
                array_push($errors, "Invalid Building Id");
            }
            unset($temp_data);
        }


        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {
            //preparing data insert/update array

            $insert_data['building_name'] = $data_array['building_name'];
            $insert_data['building_logo'] = 'buildings/'.$data_array['building_logo'];
            $insert_data['building_photo'] = 'buildings/'.$data_array['building_photo'];
            if($data_array['location_name'] == ''){
                $data_array['location_name'] = $data_array['building_name'];
            }
            $insert_data['location_name'] = $data_array['location_name'];
            //$insert_data['address'] = $data_array['address'];
            

            if($data_array['building_id'] != "")
            {
                $insert_data['updated_time'] = date('Y-m-d H:i:s');

                $sql_query = "";
                foreach($insert_data as $key => $value)
                {
                    if($sql_query == "")
                    {                        
                        $sql_query .= $key." = '".mysql_escape_string($value)."'";
                    }
                    else
                    {
                        $sql_query .= ", ".$key." = '".mysql_escape_string($value)."'";
                    }
                }

                $update_query = "UPDATE `tbl_buildings` SET ".$sql_query." WHERE building_id = ".$data_array['building_id'];
                
                $db = $this->app->db;
                $query = $db->prepare($update_query);
                $qr_result = $query->execute(); 
            }
            else
            {

                $sql_array = array();
                foreach($insert_data as $key => $value)
                {
                    $sql_array[$key] = "'".mysql_escape_string($value)."'";
                }

                $fields_str = implode(',',array_keys($sql_array));
                $values_str = implode(',',array_values($sql_array));

                $insert_query = "INSERT INTO `tbl_buildings` ($fields_str) VALUES ($values_str) ";
                $db   =   $this->app->db;
                $query = $db->prepare($insert_query);
                $qr_result = $query->execute();
            }

            if($qr_result)
            {
                
                if(isset($data_array['building_id']) && trim($data_array['building_id']) != "")
                {
                    $result = $this->app->ps_success;
                    $result['message'] = "Building Information successfully updated";
                    $lastInsertId = $data_array['building_id']; //Last Updated Id
                    $result['data'] = array( 'id' => $lastInsertId);

                }
                else
                {
                    $lastInsertId = $db->insert_id; //Last Inserted Id   
                    $result = $this->app->ps_success;
                    $result['message'] = "Building Information successfully created";
                    $result['data'] = array( 'id' => $lastInsertId);
                    $newresponse = $response->withStatus($this->app->success_status);                                  
                
                }
                                
            }
            else
            {
                $result = $this->app->ps_failed;
                $result['message'] = "Building Information insertion failed";
                $newresponse = $response->withStatus($this->app->db_failures);
            }

            $response->getBody()->write(json_encode($result));
            return $newresponse;

        }
        
    }


    /**
     * Profile Pic upload from admin
    **/
    public function upload_photo($image, $picname='',$folder) {

        $status_code = 200;
        if( !isset($picname) || empty($picname) )
        {
           $picname   =   substr( md5(rand()), 0, 16); 
        }

        $target_dir = "v1/uploads/".$folder;

        
        $imageFileType = pathinfo(basename($image["name"]),PATHINFO_EXTENSION);
        $target_file = $target_dir . "/" . $picname ."." .$imageFileType;

        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
            return 'invalid_image';
        }
        else
        {
            if (move_uploaded_file($image["tmp_name"], $target_file)) {
                return  $picname ."." .$imageFileType;
            } else {
                return 'upload_failed';
            }

        }
        
    }


    /************************************************************
    * image_uploadify()
    * This function uploads the image into corresponding upload
    * folder.
    * 
    * inputs:
    * @category - category (building/visitor)
    * 
    * output:
    * Returns image path on succesfull upload. Otherwise returns
    * the error message.
    *************************************************************/
    function image_uploadify($request,$response,$args)
    {

        $data_array = $request->getParsedBody();

        if (!empty($_FILES) ) {

            $tempFile = $_FILES['Filedata']['tmp_name'];
            $picname   =   substr( md5(rand()), 0, 16);
            
            // Validate the file type
            $fileTypes = array('jpg','jpeg','gif','png','GIF','PNG' ,'JPG','JPEG'); // File extensions
            $fileParts = pathinfo($_FILES['Filedata']['name']);

            $targetPath ='v1/uploads/buildings';
            $targetFile = rtrim($targetPath,'/') . '/' . $picname . ".".$fileParts['extension'];
            
            if (in_array($fileParts['extension'],$fileTypes)) {
                if (move_uploaded_file($tempFile,$targetFile)) {
                    $data =  $picname ."." .$fileParts['extension'];
                    $msg = "Image successfully uploaded";
                } else {
                    $msg =  'Image uploading failed';
                }
            } else {
                $msg = 'Only JPG,PNG,JPEG,GIF image types are allowed.';
            }

            $newresponse = $response->withStatus($this->app->success_status);
            $result = $this->app->ps_success;
            $result['message'] = $msg;
            $result['data'] = $data;

            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }

    }



    /*************************************************************
    * get_buildings_list()
    * This function is used to get the list of buldings
    *
    * output:
    * Returns the list of the projects.
    *************************************************************/
    public function get_buildings_list($request,$response,$args)
    {
        $req_data_array = $request->getQueryParams();       
        
        $data = array();
        $projects_query  = "SELECT tb.building_id,tb.building_name,tb.building_photo,tb.building_logo FROM tbl_buildings tb WHERE tb.delete_status=0 ORDER BY tb.building_name";

        $db  =   $this->app->db;
        $qr_result = $db->query($projects_query);

        $i = 0;
        while( $row = $qr_result->fetch_array(MYSQLI_ASSOC) ) 
        {
            if(isset($row['building_photo']) && $row['building_photo'] != ''){
                $row['building_photo'] = $this->app->base_url.'/uploads/'.$row['building_photo'];
            }
            if(isset($row['building_logo']) && $row['building_logo'] != ''){
                $row['building_logo'] = $this->app->base_url.'/uploads/'.$row['building_logo'];
            }
            $data[$i] = $row;
            $i++;
        }

        $newresponse = $response->withStatus($this->app->success_status);
        $result = $this->app->ps_success;
        $result['message'] = '';
        $result['data'] = $data;

        $response->getBody()->write(json_encode($result));
        return $newresponse;
    }



    /*************************************************************
    * get_building_details()
    * This function is used to retrieve a given project details
    * along with its project urls details list.
    *
    * inputs:
    * @id - project id
    *
    * output:
    * returns the project details on success otherwise
    * returns failure message
    *************************************************************/
    public function get_building_details($request,$response,$args)
    {
        $data_array = $request->getParsedBody();
        $result = array();
        $errors = array();

        // Building Id Validation
      
        $building_id  =  $this->app->utilities->clean_string($args,'id');

        if($building_id == '')
        {
            array_push($errors,"Building Id is required");
        }


       if($building_id != "")
       {
            $temp_data = array();
            $temp_data = $this->app->utilities->is_record_exist('tbl_buildings','building_id',$building_id," and delete_status=0");

            if(count($temp_data) == 0)
            {
                array_push($errors, "Invalid Building Id");
            }
            unset($temp_data);
       }


        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {
        
            $project_details_query = "SELECT tb.building_id,tb.building_name,tb.building_code,tb.address,tb.building_photo,tb.building_logo from tbl_buildings tb where tb.building_id='".$building_id."'";

            $db  =   $this->app->db;
            $qr_result = $db->query($project_details_query);
            
            $data = array();
            while ( $row = $qr_result->fetch_array(MYSQLI_ASSOC) ) 
            {
                $data['building_id'] = $row['building_id'];
                $data['building_name'] = $row['building_name'];
                $data['building_code'] = $row['building_code'];
                $data['address'] = $row['address'];
                if($row['building_photo'] != ''){
                   $data['building_photo'] = $this->app->base_url.'/uploads/'.$row['building_photo']; 
                }
                else{
                    $data['building_photo'] = '';
                }

                if($row['building_logo'] != ''){
                   $data['building_logo'] = $this->app->base_url.'/uploads/'.$row['building_logo']; 
                }
                else{
                    $data['building_logo'] = '';
                }
                
            }

            $newresponse = $response->withStatus($this->app->success_status);
            $result = $this->app->ps_success;
            $result['message'] = '';
            $result['data'] = $data;

            $response->getBody()->write(json_encode($result));
            return $newresponse;

        }

    }


	/*************************************************************
    * delete_building()
    * This function is used to delete a building with all details.
    *
    * inputs: 
    * @building_id - building_id
    * 
    * output: 
    * return Success message on successfull deletion
    * else returns failure
    *************************************************************/
    public function delete_building($request,$response,$args)
    {
        $data_array = $request->getParsedBody();
        $errors = array();
        $result = array();

        // Project Id Validation     
        $building_id  =  $this->app->utilities->clean_string($args,'id');

        if($building_id == '')
        {
            array_push($errors,"Building Id is required");
        }


        if($building_id != '')
        {
            //check for project existence
            $temp_data = array();
            $temp_data = $this->app->utilities->is_record_exist('tbl_buildings','building_id',$building_id," and delete_status=0");

            if(count($temp_data) == 0)
            {
                array_push($errors, "Invalid Building Id");
            }
            unset($temp_data);
        }

        
       

        if(count($errors) == 0)
        {
            // delete corresponding URLs first
            $update_url = "UPDATE `tbl_buildings` SET `delete_status` = 1, `updated_time` = '".date('Y-m-d H:i:s')."' WHERE `building_id` = ".$building_id;
            $db = $this->app->db;
            $query = $db->prepare($update_url);
            $db_result = $query->execute();   

            if($db_result)
            {
                $result = $this->app->ps_success;
                $result['message'] = "Building successfully deleted";
                $result['data'] = array( 'id' => $building_id);
                $newresponse = $response->withStatus($this->app->success_status);
            }
            else
            {
                $result = $this->app->ps_failed;
                $result['message'] = "Building deletion failed";
                $result['data'] = array('id' => $building_id);
                $newresponse = $response->withStatus($this->app->db_failures);
            }

            $response->getBody()->write(json_encode($result));
            return $newresponse;  
        }
        else
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
    }

    /*************************************************************
    * get_visitors_list()
    * This function is used to get the list of visitors
    *
    * output:
    * Returns the list of the projects.
    *************************************************************/
    public function get_visitors_list($request,$response,$args)
    {

        $req_data_array = $request->getQueryParams();

        $building_id = $this->app->utilities->clean_string($req_data_array,'building_id');
        $from_date  =  $this->app->utilities->clean_string($req_data_array,'from_date');
        $to_date  =  $this->app->utilities->clean_string($req_data_array,'to_date');

        $building_clause = '';
        if($building_id != ''){
            $building_clause = " and tv.building_id = $building_id ";
        }

        $date_clause = '';
        if($from_date != '' && $to_date != '' && $from_date == date('Y-m-d',strtotime($from_date)) && $to_date == date('Y-m-d',strtotime($to_date)) ){
            $date_clause = " and tv.check_in_time >= '".$from_date." 00:00' and tv.check_in_time <= '".$to_date." 59:59' ";
        }

        $data = array();
        $projects_query  = "SELECT tv.visitor_id,tv.visitor_name,tv.ipad_name,tv.visitor_email,tv.is_share,tv.building_id,tb.building_name,tb.location_name, tv.check_in_time, tv.check_out_time ,tv.visitor_photo,tv.purpose_of_visit,tv.visiting_person,tv.check_in_status FROM tbl_visitors tv,tbl_buildings tb where tb.building_id = tv.building_id $building_clause $date_clause ";

        $db  =   $this->app->db;
        $qr_result = $db->query($projects_query);

        $i = 0;
        while( $row = $qr_result->fetch_array(MYSQLI_ASSOC) ) 
        {
            if(isset($row['visitor_photo']) && $row['visitor_photo'] != ''){
                $row['visitor_photo'] = $this->app->base_url.'/uploads/'.$row['visitor_photo'];
            }

            if($row['check_in_time'] == '0000-00-00 00:00:00' )
            { 
                $row['check_in_time'] = ''; 
                $row['check_in_time_formatted'] = '';
            }
            else
            {
                $row['check_in_time'] = date('d-M-Y H:i',strtotime($row['check_in_time']));
                $row['check_in_time_formatted'] = date('Y-m-d H:i',strtotime($row['check_in_time']));
            }

            if($row['check_out_time'] == '0000-00-00 00:00:00' )
            { 
                $row['check_out_time'] = ''; 
                $row['check_out_time_formatted'] = '';
            }
            else
            {
                $row['check_out_time'] = date('d-M-Y H:i',strtotime($row['check_out_time']));
                $row['check_out_time_formatted'] = date('Y-m-d H:i',strtotime($row['check_out_time']));
            }

            $data[$i] = $row;
            $i++;
        }

        $newresponse = $response->withStatus($this->app->success_status);
        $result = $this->app->ps_success;
        $result['message'] = '';
        $result['data'] = $data;

        $response->getBody()->write(json_encode($result));
        return $newresponse;
    }

    /*************************************************************
    * get_visitors_autosuggest()
    * This function is used to get the list of visitors
    *
    * output:
    * Returns the list of the projects.
    *************************************************************/
    public function get_visitors_autosuggest($request,$response,$args)
    {
        $data_array = $request->getQueryParams();
        $result = array();
        $errors = array();

        $visitor_name  =  $this->app->utilities->clean_string($data_array,'visitor_name');
        $building_id = $this->app->utilities->clean_string($data_array,'building_id');

        if($visitor_name == '')
        {
            array_push($errors,"Visitor Name is required");
        }

        if($building_id == '')
        {
            array_push($errors,"Building Id is required");
        }

        if($building_id != "")
        {
            $temp_data = array();
            $temp_data = $this->app->utilities->is_record_exist('tbl_buildings','building_id',$building_id," and delete_status=0");

            if(count($temp_data) == 0)
            {
                array_push($errors, "Invalid Building Id");
            }
            unset($temp_data);
        }

        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {

            $building_clause = '';
            if($building_id != ''){
                $building_clause = " and tv.building_id = '".$building_id."' ";
            }

            //$today_start = date('Y-m-d')." 00:00:00";
            $today = date_create();
            date_sub($today,date_interval_create_from_date_string("24 hours"));
            $day_ago_date = date_format($today,'Y-m-d');

            $data = array();
            $projects_query  = "SELECT tv.visitor_id,tv.visitor_name,tv.visitor_email,tv.building_id,tv.visitor_photo FROM tbl_visitors tv where tv.visitor_name LIKE '%$visitor_name%' $building_clause and tv.check_in_status = '1' and tv.check_in_time >= '".$day_ago_date."' ";

            $db  =   $this->app->db;
            $qr_result = $db->query($projects_query);

            $i = 0;
            while( $row = $qr_result->fetch_array(MYSQLI_ASSOC) ) 
            {
                if(isset($row['visitor_photo']) && $row['visitor_photo'] != ''){
                    $row['visitor_photo'] = $this->app->base_url.'/uploads/'.$row['visitor_photo'];
                }
                $data[$i] = $row;
                $i++;
            }

            $newresponse = $response->withStatus($this->app->success_status);
            $result = $this->app->ps_success;
            $result['message'] = '';
            $result['data'] = $data;

            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }

    }


    /**************************************************************
    * check_in_visitor()
    * This function is used to create/update the project details
    *
    * inputs: 
    * @building_name - name of the building.
    * @building_code - Code Name of building
    * @address - address of building
    * @building_photo - building photo
    * 
    * output: 
    * return Success message on successful insertion
    * else returns reason on failure
    *
    ***************************************************************/
    public function check_in_visitor($request,$response,$args)
    {

        $data_array = $request->getParsedBody();
        $result = array();
        $errors = array();

        // Project Name Validation
        $data_array['visitor_name']  =  $this->app->utilities->clean_string($data_array,'visitor_name');
        $data_array['building_id'] = $this->app->utilities->clean_string($data_array,'building_id');
        $data_array['check_in_time'] = $this->app->utilities->clean_string($data_array,'check_in_time');
        $data_array['visitor_email'] = $this->app->utilities->clean_string($data_array,'visitor_email');
        $data_array['visitor_phone'] = $this->app->utilities->clean_string($data_array,'visitor_phone');
        $data_array['visitor_photo'] =  '';
        $data_array['purpose_of_visit'] = $this->app->utilities->clean_string($data_array,'purpose_of_visit');
	$data_array['visiting_person'] = $this->app->utilities->clean_string($data_array,'visiting_person');
        $data_array['ipad_name'] = $this->app->utilities->clean_string($data_array,'ipad_name');
        $data_array['time_zone'] = $this->app->utilities->clean_string($data_array,'time_zone');

        if($data_array['visitor_name'] == '')
        {
            array_push($errors,"Visitor Name is required");
        }
      
        if($data_array['building_id'] == '')
        {
            array_push($errors,"Building Id is required");
        }

        if($data_array['check_in_time'] == '')
        {
            array_push($errors,"Check-In time is required");
        }

        if( $data_array['check_in_time'] != date('Y-m-d H:i',strtotime($data_array['check_in_time'])) )
        {
           array_push($errors,"Check-In time is not a valid format");
        }

        if($data_array['visitor_phone'] == '')
        {
            array_push($errors,"Phone Number is required");
        }

        if($data_array['time_zone'] == '')
        {
            array_push($errors,"Timezone is required");
        }
     

        if($data_array['building_id'] != "")
        {
            $temp_data = array();
            $temp_data = $this->app->utilities->is_record_exist('tbl_buildings','building_id',$data_array['building_id']," and delete_status=0");

            if(count($temp_data) == 0)
            {
                array_push($errors, "Invalid Building Id");
            }
            unset($temp_data);
        }


        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {

            //preparing data insert/update array
            $insert_data['visitor_name'] = $data_array['visitor_name'];
            $insert_data['building_id'] = $data_array['building_id'];
            $insert_data['check_in_time'] = date('Y-m-d H:i',strtotime($data_array['check_in_time']));
            $insert_data['visitor_photo'] = '';
            $insert_data['purpose_of_visit'] = $data_array['purpose_of_visit'];
	    $insert_data['visiting_person'] = $data_array['visiting_person'];
            $insert_data['visitor_email'] = $data_array['visitor_email'];
            $insert_data['phone_number'] = $data_array['visitor_phone'];
            $insert_data['is_share'] = $data_array['is_share'];
            $insert_data['ipad_name'] = $data_array['ipad_name'];
            $insert_data['time_zone'] = $data_array['time_zone'];


            if(count($_FILES) > 0 && isset($_FILES['visitor_photo'])){

                $image_status = $this->upload_photo($_FILES['visitor_photo'],'','visitors');

                if($image_status == 'invalid_image'){
                    $result = $this->app->ps_failed;
                    $result['message'] = 'Only JPG,PNG,JPEG,GIF image types are allowed.';

                    $newresponse = $response->withStatus($this->app->validation_failed_status);
                    $response->getBody()->write(json_encode($result));
                    return $newresponse;
                }
                else if($image_status == 'upload_failed'){
                    $result = $this->app->ps_failed;
                    $result['message'] = 'Image uploading failed.';

                    $newresponse = $response->withStatus($this->app->validation_failed_status);
                    $response->getBody()->write(json_encode($result));
                    return $newresponse;
                }
                else{
                    $insert_data['visitor_photo'] = 'visitors/'.$image_status;
                }
            }
            
            //Insertion Query
            $sql_array = array();
            foreach($insert_data as $key => $value)
            {
                $sql_array[$key] = "'".mysql_escape_string($value)."'";
            }

            $fields_str = implode(',',array_keys($sql_array));
            $values_str = implode(',',array_values($sql_array));

            $insert_query = "INSERT INTO `tbl_visitors` ($fields_str) VALUES ($values_str) ";
            $db   =   $this->app->db;
            $query = $db->prepare($insert_query);
            $qr_result = $query->execute();


            if($qr_result)
            {
                $lastInsertId = $db->insert_id; //Last Inserted Id   
                $result = $this->app->ps_success;
                $result['message'] = "Visitor Checked in successfully.";
                $result['data'] = array( 'visitor_id' => $lastInsertId , 'check_in_time' => $insert_data['check_in_time']);
                $newresponse = $response->withStatus($this->app->success_status);                                              
            }
            else
            {
                $result = $this->app->ps_failed;
                $result['message'] = "Visitor Check in failed";
                $newresponse = $response->withStatus($this->app->db_failures);
            }

            $response->getBody()->write(json_encode($result));
            return $newresponse;

        }
        
    }


    /**************************************************************
    * check_out_visitor()
    * This function is used to checkout the visitor
    *
    * inputs: 
    * @building_name - name of the building.
    * @building_code - Code Name of building
    * @address - address of building
    * @building_photo - building photo
    * 
    * output: 
    * return Success message on successful insertion
    * else returns reason on failure
    *
    ***************************************************************/
    public function check_out_visitor($request,$response,$args)
    {

        $data_array = $request->getParsedBody();
        $result = array();
        $errors = array();

        // Project Name Validation
        $data_array['visitor_id']  =  $this->app->utilities->clean_string($data_array,'visitor_id');
        $data_array['building_id'] = $this->app->utilities->clean_string($data_array,'building_id');
        $data_array['check_out_time'] =  $this->app->utilities->clean_string($data_array,'check_out_time');


        if($data_array['visitor_id'] == '')
        {
            array_push($errors,"Visitor Id is required");
        }
      
        if($data_array['building_id'] == '')
        {
            array_push($errors,"Building Id is required");
        }

        if($data_array['check_out_time'] == '')
        {
            array_push($errors,"Check-Out time is required");
        }

        if( $data_array['check_out_time'] != date('Y-m-d H:i',strtotime($data_array['check_out_time'])) )
        {
           array_push($errors,"Check-Out time is not a valid format");
        }

        if($data_array['building_id'] != "")
        {
            $temp_data = array();
            $temp_data = $this->app->utilities->is_record_exist('tbl_buildings','building_id',$data_array['building_id']," and delete_status=0");

            if(count($temp_data) == 0)
            {
                array_push($errors, "Invalid Building Id");
            }
            unset($temp_data);
        }


        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {

            //preparing data update array
            $visitor_id = $data_array['visitor_id'];
            $building_id = $data_array['building_id'];

            $search_query  = "SELECT visitor_id,check_in_status FROM tbl_visitors WHERE  visitor_id = '$visitor_id' and building_id = '$building_id' ORDER BY visitor_id DESC";

            $db  =  $this->app->db;
            $qr_result = $db->query($search_query);
            $data = array();
            while ( $row = $qr_result->fetch_array(MYSQLI_ASSOC) ) 
            {
                $data[] = $row;
            }

            if(count($data) == 0)
            {
                $result = $this->app->ps_failed;
                $result['message'] = "No Visitor has checked in with the given information";
                $newresponse = $response->withStatus($this->app->db_failures);
            }
            else
            {
                if($data[0]['check_in_status'] == '0')
                {
                    $result = $this->app->ps_failed;
                    $result['message'] = "Visitor has already checked out.";
                    $newresponse = $response->withStatus($this->app->db_failures);
                }
                else
                {
                    $check_out_time = $data_array['check_out_time'];
                    $updated_time = date('Y-m-d H:i:s');
                    $update_url = "UPDATE `tbl_visitors` SET `check_in_status` = 0 , `check_out_time` = '".$check_out_time."' , `updated_time` = '".$updated_time."' WHERE `visitor_id` = ".$data[0]['visitor_id'];
                    $db = $this->app->db;
                    $query = $db->prepare($update_url);
                    $db_result = $query->execute();   

                    if($db_result)
                    {
                        $result = $this->app->ps_success;
                        $result['message'] = "Visitor checked out successfully";
                        $result['data'] = array( 'visitor_id' => $data[0]['visitor_id'] ,'check_out_time' => $check_out_time);
                        $newresponse = $response->withStatus($this->app->success_status);
                    }
                    else
                    {
                        $result = $this->app->ps_failed;
                        $result['message'] = "Visitor check out failed";
                        $result['data'] = array('visitor_id' => $data[0]['visitor_id']);
                        $newresponse = $response->withStatus($this->app->db_failures);
                    }
                    
                }
            }

            $response->getBody()->write(json_encode($result));
            return $newresponse;

        }
        
    }


    /*******************************************************
    * forgot_password()
    * This function checks whether the admin with the given
    * email id exists or not and then sends the password
    * reset email.
    *******************************************************/
    public function forgot_password($request,$response,$args)
    {

        $data_array = $this->app->request->getParsedBody();  
        $result = array();

        $email_id  =  $this->app->utilities->clean_string($data_array,'email_id');

        //inspect data array for required values.
        if( $email_id == "" ) 
        {

           $result = $this->app->ps_failed;
           $result['message'] = array();
           if(!isset($data_array['email_id']))
           {
                array_push($result['message'],'Email Id is required');
           }

           $newresponse = $response->withStatus($this->app->validation_failed_status);
        }
        else
        {
            //Email format validation
            $email_check = $this->app->utilities->check_email_format($data_array['email_id']);
            if(!$email_check)
            {   
               $result = $this->app->ps_failed;
               $result['message'] = array();
               array_push($result['message'],'Email Id is not a valid email address');
            }

            if(count($result['message']) > 0)
            {
                 $newresponse = $response->withStatus($this->app->validation_failed_status);
            }
            else
            {
                // check whether the email exists
                $admin_data = $this->app->utilities->is_record_exist('tbl_admins','email_id',$data_array['email_id'],'');
                if( is_array($admin_data) && count($admin_data)>0 )
                {
                    $id = $admin_data[0]['id'];
                    $data_array['hash'] = substr( md5(rand()), 0, 16);

                    $token_query = "UPDATE tbl_admins SET reset_token = '".$data_array['hash']."' where id = '".$id."' ";
                    $db = $this->app->db;
                    $query = $db->prepare($token_query);
                    $db_result = $query->execute();

                    $res = $this->send_password_reset_email($data_array);
                    if($res == '1')
                    {
                        $result = $this->app->ps_success;
                        $result['message'] = "Mail sent successfully.Please check your mail.";
                        $result['data'] = '';
                    }
                    else
                    {
                        $result = $this->app->ps_failed;
                        $result['message'] = "Sending mail has failed. Please try after sometime.";
                        $newresponse =   $response->withStatus($this->app->invalid_login);
                    }

                    $newresponse = $response->withStatus($this->app->success_status);
                } 
                else
                {
                     $result = $this->app->ps_failed;
                     $result['message'] = "Email Id doesn't exist in the database.";
                     $newresponse =   $response->withStatus($this->app->invalid_login);
                }
            }
            
        }        
       
        $response->getBody()->write(json_encode($result));
        return $newresponse;

    }

    /**
     * sending password reset link through email
    **/
    protected function send_password_reset_email($email_data) 
    {
        
        $mail_data['to'] = array($email_data['email_id']);
        $mail_data['subject']  = 'Reset your Office Group admin password';
        //api/public/v1/user/reset/password/check
        $mail_data['body']     =  '<div>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">&nbsp;</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">Hi,</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">&nbsp;</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">We received your request.</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">&nbsp;</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt"><a href="'.$this->app->email_reset_view.'?hash='.$email_data['hash'].'-'.base64_encode($email_data['email_id']).'">Click</a> this link to reset your Office Group Password. </span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">&nbsp;</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">Thanks</span></p>
            <p style="margin:0in 0in 0pt; font-family:&quot;Times New Roman&quot;,serif; font-size:12pt">
            <span style="color:rgb(31,73,125); font-family:&quot;Calibri&quot;,serif; font-size:11pt">TOG Team</span></p>
            </div>';
        return $this->app->utilities->send_email($mail_data); //send email
    }


    /**
     * Reset password 
    **/
    public function reset_password($request,$response,$args) {

        $data_array = $this->app->request->getParsedBody();  
        $result = array();

        $hash  =  $this->app->utilities->clean_string($data_array,'hash');
        $new_password  =  $this->app->utilities->clean_string($data_array,'new_password');
        $confirm_password  =  $this->app->utilities->clean_string($data_array,'confirm_password');
        
        // Validations Starts here
        $errors = array();

        if($hash == '')
        {
            array_push($errors,"Hash is missing");
        }

        if($new_password == '')
        {
            array_push($errors,"New Password is required");
        }

        if($confirm_password == '')
        {
            array_push($errors,"Confirm Password is required");
        }

        if( $new_password != '' && $confirm_password != '' && $new_password != $confirm_password )
        {
            array_push($errors,"Confirm Password must match with New Password");
        }


        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);
            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {

            $hash_array = explode('-', $hash);
            $reset_token = $hash_array[0];
            $email_id = base64_decode($hash_array[1]);

            $data = $this->app->utilities->is_record_exist('tbl_admins','reset_token',$reset_token," and email_id = '".$email_id."' ");

            if(count($data) == 0)
            {
                $result = $this->app->ps_failed;
                $result['message'] = "Password reset failed";
                $result['data'] = '';
                $newresponse = $response->withStatus($this->app->invalid_login);

            }
            else
            {
                $id = $data[0]['id'];
                $md5_pwd = md5($new_password);
                $update_url = "UPDATE tbl_admins SET password = '".$md5_pwd."', reset_token = '' ,updated_time = '".date('Y-m-d H:i:s')."' WHERE  id = '".$id."' ";
                $db = $this->app->db;
                $query = $db->prepare($update_url);
                $db_result = $query->execute();   

                if($db_result)
                {
                    $result = $this->app->ps_success;
                    $result['message'] = "Password reset successfully";
                    $result['data'] = '';
                    $newresponse = $response->withStatus($this->app->success_status);
                }
                else
                {
                    $result = $this->app->ps_failed;
                    $result['message'] = "Password reset failed";
                    $result['data'] = '';
                    $newresponse = $response->withStatus($this->app->db_failures);
                }
            }

            $response->getBody()->write(json_encode($result));
            return $newresponse;

        }
    }


    /*************************************************************
    * find_visitor()
    * This function is used to get the visitor details if already
    * exists in the database.
    *
    * output:
    * Returns the list of the projects.
    *************************************************************/
    public function find_visitor($request,$response,$args)
    {
        $data_array = $request->getQueryParams();
        $result = array();
        $errors = array();

        $visitor_phone  =  $this->app->utilities->clean_string($data_array,'phone_number');

        if($visitor_phone == '')
        {
            array_push($errors,"Phone Number is required");
        }

        if(count($errors)>0)
        {
            $result = $this->app->ps_failed;
            $result['message'] = implode(',',$errors);

            $newresponse = $response->withStatus($this->app->validation_failed_status);
            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }
        else
        {


            //$today_start = date('Y-m-d')." 00:00:00";
            $today = date_create();
            date_sub($today,date_interval_create_from_date_string("1 year"));
            $year_ago_date = date_format($today,'Y-m-d');

            $data = array();
            $projects_query  = "SELECT tv.visitor_id,tv.visitor_name,tv.visitor_email,tv.building_id,tv.visitor_photo FROM tbl_visitors tv where tv.phone_number = '$visitor_phone' and tv.check_in_time >= '".$year_ago_date."' ORDER BY created_time DESC LIMIT 1";

            $db  =   $this->app->db;
            $qr_result = $db->query($projects_query);

            $i = 0;
            while( $row = $qr_result->fetch_array(MYSQLI_ASSOC) ) 
            {
                if(isset($row['visitor_photo']) && $row['visitor_photo'] != ''){
                    $row['visitor_photo'] = $this->app->base_url.'/uploads/'.$row['visitor_photo'];
                }
                $data[$i] = $row;
                $i++;
            }

            $newresponse = $response->withStatus($this->app->success_status);
            $result = $this->app->ps_success;
            $result['message'] = '';
            $result['data'] = $data;

            $response->getBody()->write(json_encode($result));
            return $newresponse;
        }

    }


}
?>
