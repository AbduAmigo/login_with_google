<?php

 /*-----------------------------------------------------------
    ---------------------- #Authentication -----------------
    --------------------------------------------------------*/

    // Login view
    public function login()
    {
        include_once APPPATH . "vendor/autoload.php";
        // to check if the login button is enabled
        $is_enable = $this->db->select('Enable_Portal')->get('payment_settings')->row();
        // dd($is_enable);
        if ($is_enable->Enable_Portal == 0) {
            redirect(base_url());
        }
        //check if login redirect to home page
        if ($this->IsLoggedIn()) {
            redirect(base_url());
        }

        $lang = $this->session->userdata($this->site_session->__lang_h());
        $data[$this->site_session->__lang_h()] = $lang;

        // ***************************************************
        // Note: Google oAuth
        // ***************************************************
        $google_client = new Google_Client();

        $google_settings = $this->db->get('payment_settings')->row();

        $google_client->setClientId($google_settings->Google_Client_ID); //Define your ClientID
        $google_client->setClientSecret($google_settings->Google_Secret_Key); //Define your Client Secret Key
        $google_client->setRedirectUri($google_settings->Google_Redirect_URL); //Define your Redirect URL
        
        $google_client->addScope('email');

        $google_client->addScope('profile');
        if (!$this->session->userdata('access_token')) {
            $login_button = $google_client->createAuthUrl();
            $data['google_url'] = $login_button; // pass the token to the login page view [button]
            $this->LoadView_m('auth/login', $data);
        } else {
            $this->LoadView_m('auth/login', $data);
        }
        // ***************************************************
    }


    public function userLogin()
    {

        // ***************************************************
        // Note: Google oAuth
        // ***************************************************
        $google_client = new Google_Client();
        
        $google_settings = $this->db->get('payment_settings')->row();

        $google_client->setClientId($google_settings->Google_Client_ID); //Define your ClientID
        $google_client->setClientSecret($google_settings->Google_Secret_Key); //Define your Client Secret Key
        $google_client->setRedirectUri($google_settings->Google_Redirect_URL); //Define your Redirect URL
        $google_client->addScope('email');
        $google_client->addScope('profile');


        if (isset($_GET["code"])) {
            $token = $google_client->fetchAccessTokenWithAuthCode($_GET["code"]);

            if (!isset($token["error"])) {
                $google_client->setAccessToken($token['access_token']);

                $this->session->set_userdata('access_token', $token['access_token']);

                $google_service = new Google_Service_Oauth2($google_client);

                $data = $google_service->userinfo->get();

                $current_datetime = date('Y-m-d H:i:s');

                // to check if user already registered
                $data['Email'] = $data['email'];
                // dd($data["oauth_uid"]);
                $check = $this->customer_model->checkGoogleAuthentication($data);
                // dd($check);
                if ($check) {
                    // update data
                    $user_data = array(
                        'oauth_uid' => $data['id'],
                        'Fullname' => $data['given_name'] . ' ' . $data['family_name'],
                        'Email' => $data['email'],
                        'Picture' => $data['picture'],
                        'updated_at' => $current_datetime
                    );
                    $result = $this->db->where('Email', $data['email'])
                        ->update('customers', $user_data);
                    if ($result) {
                        $this->session->set_userdata('site__auth', 1);
                    } else {
                        $this->session->set_userdata('result', 118);
                    }
                } else {
                    // insert data
                    $user_data = array(
                        'oauth_uid' => $data['id'],
                        'Fullname' => $data['given_name'] . ' ' . $data['family_name'],
                        'Email'  => $data['email'],
                        'Picture' => $data['picture']
                    );

                    $result = $this->db->insert('customers', $user_data);
                    if ($result) {
                        $this->session->set_userdata('site__auth', 1);
                    } else {
                        $this->session->set_userdata('result', 118);
                    }
                }
                // to login
                $customer_data = $this->db->where('oauth_uid', $data['id'])->get('customers')->row();
                $userdata = array(
                    $this->site_session->oauth_uid() => $customer_data->oauth_uid,
                    $this->site_session->userid() => $customer_data->Customer_ID,
                    $this->site_session->username() => $customer_data->Fullname,
                    $this->site_session->email_address() => $customer_data->Email,
                    $this->site_session->email_verified() => 1,
                    $this->site_session->is_logged_in() => true
                );

                $this->session->set_userdata($userdata);
                $this->session->set_userdata('site__auth', 0);
                // user intended url
                if ($this->site_session->is_logged_in() == true) {
                    // dd('A');
                    redirect(base_url('cu/profile'));
                }
                // ends
            }
        }
        // ***************************************************

        redirect('login');
    }

    ?>