<?php
/**
* Plugin Name: Job Manager by Jobsoid
* Description: Integrate Jobsoid with your company website. All job openings published in your Jobsoid Account will automatically be shown on your website.
* Version: 2.1.0
* Author: Jobsoid Inc.
* Author URI: https://www.jobsoid.com
*/

class JobsoidPlugin {
    private $locations;
    private $departments;

    public function __construct() {
        $this->register_plugin();
    }
    
    // Shortcode
    public function display($attrs) {
        global $jobsoid_output;
        
        if ($jobsoid_output != "") {
            return $jobsoid_output;
        }

        parse_str($_SERVER['QUERY_STRING'], $params);
        
        if (isset($params['job-id'])) {
            return $this->display_job($params['job-id']);
        }
        else {
            return $this->display_jobs($attrs);
        }
    }
    
    // Careers Page
    public function display_jobsoid_content($attrs = null) {
        global $post;
        global $jobsoid_output;

        $jobsoid_page = get_option('jobsoid-jobs-page');
        if(get_the_ID() == $jobsoid_page) {
            if (!is_object($post)) {
                $post = new stdClass();
            }
            if (get_query_var('job-id')) {
                $job = $this->get_job(get_query_var('job-id'));

                $post->post_title = $job['title'];
                $post->post_content = $jobsoid_output = $this->output_job_description($job, false) . $this->output_footer();
            }else {
                $post->post_content = $jobsoid_output = $this->display_jobs($attrs);
            }
        }
    }

    public function display_jobs($attrs) {
        $out_jobs = '';
        $loc = null;
        $dept = null;

        if(isset($_POST['dept_value'])){
            $dept = $_POST['dept_value'];
        }
        elseif(isset($attrs) && ($attrs != null && array_key_exists('department', $attrs))){
            $dept = $attrs['department'];
        }
        elseif (get_option('jobsoid-department') != ""){
            $dept = get_option('jobsoid-department');
        }
        
        if(isset($_POST['loc_value'])){
            $loc = $_POST['loc_value'];
        }
        elseif(isset($attrs) && ($attrs != null && array_key_exists('location', $attrs))){
            $loc = $attrs['location'];
        }
        elseif (get_option('jobsoid-location') != ""){
            $loc = get_option('jobsoid-location');
        }
        
        $groupby = get_option('jobsoid-group-by');
        if ($groupby == "") {
            $groupby = "AUTO";
        }

        $jobs = $this->get_jobs($dept, $loc);

        switch ($groupby) {
            case 'NONE':
                $out_jobs .= $this->output_jobs($jobs, $dept, $loc, $attrs);
                break;
            
            case 'DEPARTMENT':
                $out_jobs .= $this->output_jobs_by_dept($jobs, $dept, $loc, $attrs);
                break;
            
            case 'LOCATION':
                $out_jobs .= $this->output_jobs_by_location($jobs, $dept, $loc, $attrs);
                break;
            
            default:
                $out_jobs .= $this->output_jobs_by_auto($jobs, $dept, $loc, $attrs);
                break;
        }
        
        if(wp_doing_ajax()){
            echo $out_jobs;
            die();
        }
        
        $out = $this->output_filter_form() . $out_jobs . $this->output_footer();
        return $out;
    }

    public function display_job($jobId) {
        $out = '';
        $job = $this->get_job($jobId);
        $out .= $this->output_job_description($job);
        $out .= $this->output_footer();
        return $out;
    }

    public function display_settings() {
        flush_rewrite_rules();

        echo '<div class="wrap">';
            echo '<form id="jobsoid_settings_form" action="options.php" method="post">';
                settings_fields('jobsoid_options_group');
                do_settings_sections('jobsoid_options_group');
                echo '<div class="jobsoid-row">';
                    echo '<div class="jobsoid-col-lg-9">';
                        echo '<div style="text-align: center"';
                            echo '<h1><span style="display: inline-block;"><img src="' . plugin_dir_url(__FILE__) . 'img/jobsoid_logo_sm.png"/></span></h1>';
                            echo '<h3>Online Applicant Tracking System</h3>';
                            echo '<p>Streamline every step of your recruiting process, collaborating with your entire team in real-time. Advertise jobs, source and manage candidates and hire the best with our online Talent Acquisition Platform designed to make you more productive every day.</p>';
                            echo '<h4>Sign Up for a Free Account at <a href="http://www.jobsoid.com" target="_blank">www.jobsoid.com</a></h4>';
                        echo '</div>';
                        
                        echo '<div class="jobsoid-panel" id="portal-subdomain">';
                            echo $this->output_portal_subdomain();
                        echo '</div>';
                        if ($this->get_company_code()) {
                            
                            $this->departments = $this->get_departments();
                            $this->locations = $this->get_locations();
                            
                            echo '<div class="jobsoid-panel" id="page-settings">';
                                echo $this->output_page_settings();
                                echo '<div class="jobsoid-submit">';
                                    // submit_button();
                                    submit_button('Save Settings', 'primary', 'jobsoid-save-settings', true, array('id' => 'jobsoid_page_save'));
                                echo '</div>';
                            echo '</div>';
                            echo '<div class="jobsoid-panel" id="generate-shortcode">';
                                echo $this->output_generate_shortcode();
                            echo '</div>';
                        }
                    echo '</div>';
                echo '</div>';
            echo '</form>';
        echo '</div>';
        echo '<script>(function($) { $(document).ready(function(){JobsoidAdmin.init();}); })(jQuery);</script>';
        
    }

    public function register_plugin() {
        if (is_admin()) {
            add_action('admin_menu', array($this,'register_plugin_menu'));
            add_action('admin_init', array($this,'register_plugin_init'));
            add_action('init', array($this,'register_rewrite_rule'));
        }
        
        add_action('template_redirect', array($this,'display_jobsoid_content'));
        
        add_action('wp_enqueue_scripts', array($this,'register_front_js'));
        add_action('admin_enqueue_scripts', array($this,'register_back_js'));

        add_action('wp_enqueue_scripts', array($this,'register_styles'));
        add_action('admin_enqueue_scripts', array($this,'register_back_styles'));
        
        add_filter('body_class',array($this, 'theme_class_names'));
        
        add_action('wp_ajax_filter', array($this, 'display_jobs'));
        add_action('wp_ajax_nopriv_filter', array($this, 'display_jobs')); // for use outside admin
        
        add_action('wp_ajax_reset', array($this, 'display_jobs'));
        add_action('wp_ajax_nopriv_reset', array($this, 'display_jobs'));
        
        add_filter('query_vars', function ($vars) {
            $vars[] = "job-id";
            return $vars;
        });
        
        $this->register_shortcode();
    }

    public function theme_class_names($classes) {
        $theme = get_option( 'jobsoid-theme');
        $classes[] = esc_attr($theme);
        return $classes;
    }

    public function register_rewrite_rule() {
        $jobsoid_page_id = get_option('jobsoid-jobs-page');
        
        if ($jobsoid_page_id && $jobsoid_page_id > 0) {
            add_rewrite_rule('^' . get_page_uri($jobsoid_page_id) . '/([0-9]+)/?', 'index.php?page_id=' . $jobsoid_page_id . '&job-id=$matches[1]', 'top');
            add_rewrite_tag('%' . get_page_uri($jobsoid_page_id) . '%', '([^&]+)');
        }
    }

    public function register_plugin_menu() {
        add_menu_page('Jobsoid', 'Jobsoid ', 'administrator', 'jobsoid', array($this, 'display_settings') , plugins_url('jobsoid/img/favicon.png'));
    }

    public function register_plugin_init() {
        register_setting('jobsoid_options_group', 'jobsoid-company-code');
        register_setting('jobsoid_options_group', 'jobsoid-group-by');
        register_setting('jobsoid_options_group', 'jobsoid-jobs-page');
        register_setting('jobsoid_options_group', 'jobsoid-department');
        register_setting('jobsoid_options_group', 'jobsoid-location');
        register_setting('jobsoid_options_group', 'jobsoid-division');
        register_setting('jobsoid_options_group', 'jobsoid-layout');
        register_setting('jobsoid_options_group', 'jobsoid-theme');
    }

    public function register_shortcode() {
        add_shortcode('jobsoid', array($this,'display'));
    }

    public function register_back_js() {
        // wp_register_script('jquery-cdn', 'https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js', '', '1.9.1', true);
        // wp_enqueue_script('jquery-cdn');
        
        wp_register_script('jobsoid_admin_js', plugin_dir_url(__FILE__) . 'js/jobsoid.min.js', array('jquery', 'jobsoid_select2_js'), '1.0', true);
        wp_localize_script('jobsoid_admin_js', 'jobsoid_ajax_object', array('ajax_url' => $this->get_ajax_api_url() . "/jobs"));
        wp_enqueue_script('jobsoid_admin_js');

        wp_register_script('jobsoid_select2_js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js', array('jquery'), '1.0', true);
        wp_enqueue_script('jobsoid_select2_js');
        
    }

    public function register_front_js() {
        wp_register_script('jobsoid_js', plugin_dir_url(__FILE__) . 'js/jobsoid.min.js', array('jquery', 'jobsoid_select2_js'), '1.0', true);
        wp_localize_script('jobsoid_js', 'filter_ajax', array('ajax_url' => admin_url('admin-ajax.php'), 'check_nonce' => wp_create_nonce('filter-nonce')));
        wp_enqueue_script('jobsoid_js');
        
        wp_register_script('jobsoid_select2_js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js', array('jquery'), '1.0', true);
        wp_enqueue_script('jobsoid_select2_js');
    }

    public function register_styles() {
        
        wp_register_style('jobsoid_css', plugin_dir_url(__FILE__) . 'css/jobsoid.css', false, '1.0');
        wp_enqueue_style('jobsoid_css');
        
        wp_register_style('jobsoid_theme_css', plugin_dir_url(__FILE__) . 'css/jobsoid-plugin-theme.css');
        wp_enqueue_style('jobsoid_theme_css');
        
        wp_register_style('jobsoid_font', plugin_dir_url(__FILE__) . 'css/jobsoid-font-4.7.css');
        wp_enqueue_style('jobsoid_font');
        
        wp_register_style('jobsoid_bootstrap_css', plugin_dir_url(__FILE__) . 'css/bootstrap.min.css');
        wp_enqueue_style('jobsoid_bootstrap_css');

        wp_register_style('jobsoid_select2_css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css');
        wp_enqueue_style('jobsoid_select2_css');
    }

    public function register_back_styles() {
        wp_register_style('jobsoid_admin_css', plugin_dir_url(__FILE__) . 'css/jobsoid-admin.css', false, '1.0');
        wp_enqueue_style('jobsoid_admin_css');

        wp_register_style('jobsoid_select2_css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css');
        wp_enqueue_style('jobsoid_select2_css');
    }

    private function output_jobs($jobs, $dept, $loc, $attrs) {
        $out = '<div id="jobsoid-jobs">';
        if($jobs){
            $out .= '<div id="jobsoid-jobs-none">';
            $out .= $this->output_job_list($jobs, $attrs);
            $out .= '</div>';
        }
        else{
            $out .= '<h3>No Current Openings</h3>';
        }
        
        $out .='</div>';
        return $out;
    }

    private function output_job_list($jobs, $show_location = true, $show_dept = true, $attrs = null) {
        $jobsoid_page_id = get_option('jobsoid-jobs-page');
        
        if (isset($attrs) && ($attrs != null && array_key_exists('layout', $attrs))) {
            $layout = $attrs['layout'];
        }
        else {
            $layout = get_option('jobsoid-layout');
        }
        
        $out = '';
        if ($layout == 'TILED') {
            $out .= '<ul class="jobsoid-job-list jobsoid-tiled">';
        }
        else {
            $out .= '<ul class="jobsoid-job-list">';
        }
        for ($i = 0; $i < count($jobs); $i++) {
            $out .= '<li class="jobsoid-job">';
            if ($jobsoid_page_id) {
                $out .= '<a class="jobsoid-job-title" href="/' . get_page_uri($jobsoid_page_id) . '/' . $jobs[$i]['id'] . '">' . $jobs[$i]['title'] . '</a>';
            }
            else {
                $out .= '<a class="jobsoid-job-title" href="?job-id=' . $jobs[$i]['id'] . '">' . $jobs[$i]['title'] . '</a>';
            }
            $out .= '<div class="jobsoid-job-subtitle">';
            if ($show_location) {
                $out .= '<span><i class="tek-address"></i>' . $jobs[$i]['location']->title .'</span>';
            }
            if ($show_dept) {
                $out .= '<span><i class="tek-building"></i>' . $jobs[$i]['department']->title .'</span>';
            }
            $out .= '</div>';
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }

    private function output_jobs_by_dept($jobs, $dept, $loc, $attrs) {
        $out = '';
        $result = array();
        foreach ($jobs as $job)
        {
            $department = $job['department']->title;
            if (isset($result[$department])) {
                $result[$department][] = $job;
            } else {
                $result[$department] = array($job);
            }
        }
        $out .= '<div id="jobsoid-jobs">';
        if($result){
            foreach ($result as $department => $group) {
                $out .= '<h3>' . $department . '</h3>';
                $out .= $this->output_job_list($group, true, false, $attrs);
            }
        }
        else{
            $out .= '<h3>No Current Openings</h3>';
        }
        $out .= '</div>';
        return $out;
    }

    private function output_jobs_by_location($jobs, $dept, $loc, $attrs) {
        $out = '';
        $result = array();
        foreach ($jobs as $job)
        {
            $location = $job['location']->title;
            if (isset($result[$location])) {
                $result[$location][] = $job;
            } else {
                $result[$location] = array($job);
            }
        }
        $out .= '<div id="jobsoid-jobs">';
        if($result){
            foreach ($result as $location => $group) {
                $out .= '<h3>' . $location . '</h3>';
                $out .= $this->output_job_list($group, false, true, $attrs);
            }
        }
        else{
            $out .= '<h3>No Current Openings</h3>';
        }
        $out .= '</div>';
        return $out;
    }

    private function output_jobs_by_auto($jobs, $dept, $loc, $attrs) {
        $out ='';
        $result_department = array();
        $result_location = array();
        
        foreach ($jobs as $job) {
            $department = $job['department']->title;
            $location = $job['location']->title;
            if (isset($result_department[$department])) {
                $result_department[$department][] = $job;
            }
            else {
                $result_department[$department] = array($job);
            }
            
            if (isset($result_location[$location])) {
                $result_location[$location][] = $job;
            }
            else {
                $result_location[$location] = array($job);
            }
        }
        $count_dept = count($result_department);
        $count_loc = count($result_location);
        
        if ($count_loc > 1) {
            $out .= $this->output_jobs_by_location($jobs, $dept, $loc, $attrs);
        }
        
        elseif ($count_dept > 1) {
            $out .= $this->output_jobs_by_dept($jobs, $dept, $loc, $attrs);
        }
        
        else {
            $out .= $this->output_jobs($jobs, $dept, $loc, $attrs);
        }
        return $out;
    }

    private function output_job_detail_item($name, $value) {
        $out = '';
        $out .= '<div class="jobsoid-job-details-item">';
        $out .= '<strong>' . $name . '</strong>';
        $out .= '<p>' . $value . '</p>';
        $out .= '</div>';
        return $out;
    }

    private function output_job_description($job, $show_title = true) {
        $out = '';
        $out .= '<div class="jobsoid-container">';
        $out .= '<div class="jobsoid-job-details">';
        if ($show_title) {
            $out .= '<h1 class="jobsoid-job-details-title">';
            $out .= $job['title'];
            $out .= '</h1>';
        }
        
        $out .= '<div class="jobsoid-job-details-item-row">';
        $out .= $this->output_job_detail_item('Posted Date', date('d-m-y', strtotime($job['postedDate'])));
        $out .= $this->output_job_detail_item('Location', $job['location']->title);
        $out .= $this->output_job_detail_item('Department', $job['department']->title);
        
        if (!empty($job['function']->title)) $out .= $this->output_job_detail_item('Function', $job['function']->title);
        if (!empty($job['type']->title)) $out .= $this->output_job_detail_item('Job Type', $job['type']);
        
        if ($job['positions'] > 0) $out .= $this->output_job_detail_item('Positions', $job['positions']);
        
        if (!empty($job['experience']->title)) $out .= $this->output_job_detail_item('Experience', $job['experience']);
        if (!empty($job['salary']->title)) $out .= $this->output_job_detail_item('Salary', $job['salary']);
        $out .= '</div>';
        
        $out .= '<div class="jobsoid-job-details-description">';
        $out .= '<p>' . $job['description'] . '</p>';
        $out .= '</div>';
        
        $out .= '<div class="jobsoid-job-details-button">';
        $out .= '<a class="btn btn-default btn-primary" href="' . $job['applyUrl'].'/'.$job['slug']. '?source=website" target="_blank">APPLY NOW</a>';
        // $out .= '<button class="btn btn-default" id="backbtn">BACK</button>';
        $out .= '</div>';
        $out .= '</div>';
        
        return $out;
    }

    private function output_footer() {
        $out = '';
        $out .= '<div class="jobsoid-footer">';
        $out .= '<a href="https://www.jobsoid.com" target="_blank" title="Jobsoid - Applicant Tracking System & Recruiting Software">';
        $out .= ' <span class="powered-by">Powered by</span>';
        $out .= ' <span class="logo">';
        $out .= ' <i class="tek-logo"></i><i class="tek-jobsoid-logo"></i>';
        $out .= ' </span>';
        $out .= '</a>';
        $out .= '</div>';
        $out .= '</div>';
        
        return $out;
    }

    private function output_filter_form(){
        $out = '';

        $departments = $this->get_departments();
        $locations = $this->get_locations();
        
        $location_name = $this->get_location();
        $department_name = $this->get_department();
        
        $out .= '<div class="jobsoid-container">';
        $out .= '<div class="jobsoid-header">';
        $out .= '<div class="jobsoid-filter">';
        $out .= '<div class="row">';
        if(!$department_name){
            $out .= '<div class="col-md-10">';
            $out .= '<select id="jobsoid_filter_department" class="jobsoid_select2">';
                $out .= '<option value="" ' . get_option('jobsoid-department' , "") . '>All Departments</option>';
                foreach ($departments as $dept) {
                    $out .= '<option value="' . $dept['id'] . '" ' . selected($dept['id'], get_option('jobsoid-department')) . '>' . $dept['title'] . '</option>';
                }
            $out .= '</select>';
            $out .= '</div>';
        }
        
        if(!$location_name){
            $out .= '<div class="col-md-10">';
            $out .= '<select id="jobsoid_filter_location" class="jobsoid_select2">';
                $out .= '<option value="" ' . get_option('jobsoid-location') . '>All Locations</option>';
                foreach ($locations as $loc) {
                    $out .= '<option value="' . $loc['id'] . '" ' . selected($loc['id'], get_option('jobsoid-location')) . '>' . $loc['title'] . '</option>';
                }
            $out .= '</select>';
            $out .= '</div>';
        }
        $out .= '<div class="col-md-4">';
            $out .= '<button id="jobsoid_filter_reset" class="btn btn-default btn-block">';
            $out .= '<i class="tek-arrow-ccw2"></i>';
            $out .= '</button>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= '<script>(function($) { $(document).ready(function(){Jobsoid.init();}); })(jQuery);</script>';
        return $out;
    }

    private function output_portal_subdomain() {
        $jobsoid_http = "https://";
        $jobsoid_subdomain = "jobsoid.com";
        echo '<div class="jobsoid-panel-heading">';
            echo '<h2 class="jobsoid-panel-title">Portal Subdomain</h2>';
        echo '</div>';
        echo '<div class="jobsoid-panel-body">';
            echo '<p>Your Company Code is the sub-domain name or the slug name that you use for your Jobsoid Careers Portal. <a href="https://help.jobsoid.com/article/68-website-integration-script">Learn More</a></p>';
            echo '<div class="jobsoid-form-group">';
            if ($this->get_company_code()) {
                echo '<input id="jobsoid_company_code" name="jobsoid-company-code" type="text" readonly value="' . esc_attr(get_option('jobsoid-company-code')) . '" class="jobsoid-form-control jobsoid-code-readonly jobsoid-subdomain-url m-space" />';
                echo '<span class="jobsoid-http">'. $jobsoid_http .'</span>';
                echo '<span class="jobsoid-subdomain">'. $jobsoid_subdomain .'</span>';
            }
            else {
                echo '<input id="jobsoid_company_code" name="jobsoid-company-code" type="text" value="' . esc_attr(get_option('jobsoid-company-code')) . '" class="jobsoid-form-control jobsoid-subdomain-url m-space" />';
                echo '<span class="jobsoid-http">'. $jobsoid_http .'</span>';
                echo '<span class="jobsoid-subdomain">'. $jobsoid_subdomain .'</span>';
            }
            echo '</div>';
            echo '<div>';
                if ($this->get_company_code()) {
                    echo '<button type="button" id="jobsoid_disconnect" class="button button-primary">Disconnect</button>';
                }
                else {
                    echo '<button type="button" id="jobsoid_connect" class="button button-primary">Connect</button>';
                    echo '<span id="jobsoid_connect_status_msg" class="company-error"></span>';
                }
            echo '</div>';
        echo '</div>';
    }

    private function output_page_settings() {
        $pages = get_pages();
        
        echo '<div class="jobsoid-panel-heading">';
            echo '<h2 class="jobsoid-panel-title">Page Settings</h2>';
        echo '</div>';
        echo '<div class="jobsoid-panel-body">';
        echo '<table class="form-table jobsoid-form-table">';
        echo '<tbody>';
        echo '<tr valign="top">';
        echo '<th scope="row">Page</th>';
        echo '<td>';
        echo '<select name="jobsoid-jobs-page" class="jobsoid_select2" style="width: 100%;">';
        echo '<option value="" ' . selected(get_option('jobsoid-jobs-page') , "") . '>-- Select Careers Page --</option>';
        foreach ($pages as $page) {
            echo '<option value="' . $page->ID . '" ' . selected($page->ID, get_option('jobsoid-jobs-page')) . '>' . $page->post_title . '</option>';
        }
        echo '</select>';
        
        echo '</td>';
        echo '</tr>';
        echo '<tr valign="top">';
        echo '<th scope="row">Group Jobs By</th>';
        echo '<td>';
        echo '<div class="jobsoid-row">';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" name="jobsoid-group-by" value="AUTO" ';
        if(get_option('jobsoid-group-by') == ''){
            echo ' checked="checked" ';
        }else{
            echo checked("AUTO", get_option('jobsoid-group-by') , false) . '>';
        }
        echo '<span class="jobsoid-radio-check"></span>';
        
        
        echo '<strong>Auto</strong><br>';
        echo '<small>Auto Grouping of Jobs</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" name="jobsoid-group-by" value="NONE" ' . checked("NONE", get_option('jobsoid-group-by') , false) . '><span class="jobsoid-radio-check"></span>';
        echo '<strong>None</strong><br>';
        echo '<small>No Grouping of Jobs</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" name="jobsoid-group-by" value="DEPARTMENT" ' . checked("DEPARTMENT", get_option('jobsoid-group-by') , false) . '><span class="jobsoid-radio-check"></span>';
        echo '<strong>Department</strong><br>';
        echo '<small>Groups Jobs By Departments</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" name="jobsoid-group-by" value="LOCATION" ' . checked("LOCATION", get_option('jobsoid-group-by') , false) . '><span class="jobsoid-radio-check"></span>';
        echo '<strong>Location</strong><br>';
        echo '<small>Groups Jobs By Locations</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '<tr valign="top">';
        echo '<th scope="row">Layout</th>';
        echo '<td>';
        echo '<div class="jobsoid-row">';
        echo '<div class="jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" checked="checked" name="jobsoid-layout" value="STACKED" ' . checked("STACKED", get_option('jobsoid-layout') , false) . '><span class="jobsoid-radio-check"></span>';
        echo '<div class="jobsoid-layout">';
        echo '<img src="' . plugin_dir_url(__FILE__) . 'img/layout-stacked.png"/>';
        echo '<strong>Stacked</strong><br>';
        echo '<small>Displays a full width layout of Jobs</small>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" name="jobsoid-layout" value="TILED" ' . checked("TILED", get_option('jobsoid-layout') , false) . '><span class="jobsoid-radio-check"></span>';
        echo '<div class="jobsoid-layout">';
        echo '<img src="' . plugin_dir_url(__FILE__) . 'img/layout-tiled.png"/>';
        echo '<strong>Tiled</strong><br>';
        echo '<small>Displays a boxed layout of Jobs</small>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '<tr valign="top">';
        echo '<th scope="row">Department</th>';
        echo '<td>';
        echo '<select name="jobsoid-department" class="jobsoid_select2" style="width: 100%;">';
        echo '<option value="" ' . selected(get_option('jobsoid-department') , "") . '>All Departments</option>';
        foreach ($this->departments as $dept) {
            echo '<option value="' . $dept['id'] . '" ' . selected($dept['id'], get_option('jobsoid-department')) . '>' . $dept['title'] . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr valign="top">';
        echo '<th scope="row">Location</th>';
        echo '<td>';
        echo '<select name="jobsoid-location" class="jobsoid_select2" id="" style="width: 100%;">';
        
        echo '<option value="" ' . selected(get_option('jobsoid-location') , "") . '>All Locations</option>';
        foreach ($this->locations as $loc) {
            echo '<option value="' . $loc['id'] . '" ' . selected($loc['id'], get_option('jobsoid-location')) . '>' . $loc['title'] . '</option>';
        }
        echo '</select>';
        echo '</td>';
        
        //Plugin Color Schemes
        echo '<tr valign="top">';
        echo '<th scope="row">Color Themes</th>';
        echo '<td>';
        
        echo '<div class="jobsoid_page_theme color-option">';
        
        echo '<input type="radio" name="jobsoid-theme" value="t" ';
        if(get_option('jobsoid-theme') == ''){
            echo ' checked="checked" ';
        }else{
            echo checked("theme-default", get_option('') , false) . '>';
        }
        echo '<input type="hidden" class="css_url" value="" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#82878c&quot;,&quot;focus&quot;:&quot;#00a0d2&quot;,&quot;current&quot;:&quot;#fff&quot;}}" />';
        echo '<label for="jobsoid_color_bold">None</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #000">&nbsp;</td>';
        echo '<td style="background-color: #EDEDED">&nbsp;</td>';;
        echo '<td style="background-color: #000">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        
        echo '<div class="jobsoid_page_theme color-option">';
        echo '<input type="radio" name="jobsoid-theme" value="theme-bold" ' . checked("theme-bold", get_option('jobsoid-theme') , false) . '>';
        echo '<input type="hidden" class="css_url" value="" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#82878c&quot;,&quot;focus&quot;:&quot;#00a0d2&quot;,&quot;current&quot;:&quot;#fff&quot;}}" />';
        echo '<label for="jobsoid_color_bold">Bold</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #4d4d4d">&nbsp;</td>';
        echo '<td style="background-color: #EB5B51">&nbsp;</td>';
        echo '<td style="background-color: #EDEDED">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        
        echo '<div class="jobsoid_page_theme color-option">';
        echo '<input type="radio" name="jobsoid-theme" value="theme-default" ' . checked("theme-default", get_option('jobsoid-theme') , false) . '>';
        
        echo '<input type="hidden" class="css_url" value="/wp-admin/css/colors/light/colors.min.css" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#999&quot;,&quot;focus&quot;:&quot;#ccc&quot;,&quot;current&quot;:&quot;#ccc&quot;}}" />';
        echo '<label for="jobsoid_color_default">Default</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #24537B">&nbsp;</td>';
        echo '<td style="background-color: #4b96e6">&nbsp;</td>';
        echo '<td style="background-color: #E6E6E5">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        
        
        echo '<div class="jobsoid_page_theme color-option">';
        echo '<input type="radio" name="jobsoid-theme" value="theme-calm" ' . checked("theme-calm", get_option('jobsoid-theme') , false) . '>';
        echo '<input type="hidden" class="css_url" value="/wp-admin/css/colors/blue/colors.min.css" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#e5f8ff&quot;,&quot;focus&quot;:&quot;#fff&quot;,&quot;current&quot;:&quot;#fff&quot;}}" />';
        echo '<label for="jobsoid_color_calm">Calm</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #1A535C">&nbsp;</td>';
        echo '<td style="background-color: #4ECDC4">&nbsp;</td>';
        echo '<td style="background-color: #F7FFF7">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        echo '<div class="jobsoid_page_theme color-option">';
        echo '<input type="radio" name="jobsoid-theme" value="theme-woody" ' . checked("theme-woody", get_option('jobsoid-theme') , false) . '>';
        echo '<input type="hidden" class="css_url" value="/wp-admin/css/colors/coffee/colors.min.css" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#f3f2f1&quot;,&quot;focus&quot;:&quot;#fff&quot;,&quot;current&quot;:&quot;#fff&quot;}}" />';
        echo '<label for="jobsoid_color_woody">Woody</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #363020">&nbsp;</td>';
        echo '<td style="background-color: #A49966">&nbsp;</td>';
        echo '<td style="background-color: #EAFFDA">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        
        echo '<div class="jobsoid_page_theme color-option">';
        echo '<input type="radio" name="jobsoid-theme" value="theme-tangy" ' . checked("theme-tangy", get_option('jobsoid-theme') , false) . '>';
        echo '<input type="hidden" class="css_url" value="/wp-admin/css/colors/ectoplasm/colors.min.css" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#ece6f6&quot;,&quot;focus&quot;:&quot;#fff&quot;,&quot;current&quot;:&quot;#fff&quot;}}" />';
        echo '<label for="jobsoid_color_tangy">Tangy</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #5D675B">&nbsp;</td>';
        echo '<td style="background-color: #F78E69">&nbsp;</td>';
        echo '<td style="background-color: #F9E6D3">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        
        echo '<div class="jobsoid_page_theme color-option">';
        echo '<input type="radio" name="jobsoid-theme" value="theme-fresh" ' . checked("theme-fresh", get_option('jobsoid-theme') , false) . '>';
        echo '<input type="hidden" class="css_url" value="/wp-admin/css/colors/ectoplasm/colors.min.css" />';
        echo '<input type="hidden" class="icon_colors" value="{&quot;icons&quot;:{&quot;base&quot;:&quot;#ece6f6&quot;,&quot;focus&quot;:&quot;#fff&quot;,&quot;current&quot;:&quot;#fff&quot;}}" />';
        echo '<label for="jobsoid_color_fresh">Fresh</label>';
        echo '<table class="color-palette">';
        echo '<tr>';
        echo '<td style="background-color: #24537B">&nbsp;</td>';
        echo '<td style="background-color: #3EAF5A">&nbsp;</td>';
        echo '<td style="background-color: #E6E6E5">&nbsp;</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    private function output_generate_shortcode() {
        echo '<div class="jobsoid-panel-heading">';
        echo '<h2 class="jobsoid-panel-title">Generate Shortcode</h2>';
        echo '</div>';
        echo '<div class="jobsoid-panel-body">';
        echo '<table class="form-table jobsoid-form-table">';
        echo '<tbody>';
        
        echo '<tr valign="top">';
        echo '<th scope="row">Group Jobs By</th>';
        echo '<td>';
        echo '<div class="jobsoid-row">';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" class="jobsoid_shortcode_group_by" name="jobsoid-shortcode-groupby" value="AUTO" checked="checked">';
        
        echo '<span class="jobsoid-radio-check"></span>';
        
        echo '<strong>Auto</strong><br>';
        echo '<small>Auto Grouping of Jobs</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        
        echo '<input type="radio" class="jobsoid_shortcode_group_by" name="jobsoid-shortcode-groupby" value="NONE"><span class="jobsoid-radio-check"></span>';
        echo '<strong>None</strong><br>';
        echo '<small>No Grouping of Jobs</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" class="jobsoid_shortcode_group_by" name="jobsoid-shortcode-groupby" value="DEPARTMENT"><span class="jobsoid-radio-check"></span>';
        echo '<strong>Department</strong><br>';
        echo '<small>Groups Jobs By Departments</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-md-3 jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" class="jobsoid_shortcode_group_by" name="jobsoid-shortcode-groupby" value="LOCATION"><span class="jobsoid-radio-check"></span>';
        echo '<strong>Location</strong><br>';
        echo '<small>Groups Jobs By Locations</small>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr valign="top">';
        echo '<th scope="row">Layout</th>';
        echo '<td>';
        echo '<div class="jobsoid-row">';
        echo '<div class="jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" class="jobsoid_shortcode_layout" name="jobsoid-shortcode-layout" checked="checked" value="STACKED"><span class="jobsoid-radio-check"></span>';
        echo '<div class="jobsoid-layout">';
        echo '<img src="' . plugin_dir_url(__FILE__) . 'img/layout-stacked.png"/>';
        echo '<strong>Stacked</strong><br>';
        echo '<small>Displays a full width layout of Jobs</small>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '<div class="jobsoid-col-sm-6">';
        echo '<div class="jobsoid-radio-block">';
        echo '<label class="jobsoid-checkbox">';
        echo '<input type="radio" class="jobsoid_shortcode_layout" name="jobsoid-shortcode-layout" value="TILED"><span class="jobsoid-radio-check"></span>';
        echo '<div class="jobsoid-layout">';
        echo '<img src="' . plugin_dir_url(__FILE__) . 'img/layout-tiled.png"/>';
        echo '<strong>Tiled</strong><br>';
        echo '<small>Displays a boxed width layout of Jobs</small>';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        echo '<tr valign="top">';
        echo '<th scope="row">Department</th>';
        echo '<td>';
        echo '<select id="jobsoid_shortcode_department" class="jobsoid_select2" name="jobsoid-shortcode-department" style="width: 100%;">';
        echo '<option value="">All Departments</option>';
        foreach ($this->departments as $dept) {
            echo '<option value="' . $dept['id'] . '">' . $dept['title'] . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr valign="top">';
        echo '<th scope="row">Location</th>';
        echo '<td>';
        echo '<select id="jobsoid_shortcode_location" class="jobsoid_select2" name="jobsoid-shortcode-location" style="width: 100%;">';
        
        echo '<option value="">All Locations</option>';
        foreach ($this->locations as $loc) {
            echo '<option value="' . $loc['id'] . '">' . $loc['title'] . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        
        echo '<div class="jobsoid-well">' ;
        echo '<center>' ;
        echo '<code id="jobsoid_shortcode"></code>' ;
        echo '</center>' ;
        echo '</div>' ;
        echo '</div>';
        echo '<div class="jobsoid-panel-footer jobsoid-text-center">';
        echo '<button type="button" class="jobsoid-button" id="jobsoid_copy_shortcode">Copy Shortcode</button>';
        echo '</div>';
    }

    private function get_company_code() {
        $code = esc_attr(get_option('jobsoid-company-code'));
        if ($code != false) {
            return esc_attr(get_option('jobsoid-company-code'));
        }
        else {
            if(!is_admin() && trim($code)==""){
                echo '<p style="color:red">Please Configure Jobsoid Plugin!</p>';
                exit;
            }
        }
    }

    private function get_group_by() {
        return get_option('jobsoid-group-by');
    }

    private function get_department() {
        return get_option('jobsoid-department');
    }

    private function get_location() {
        return get_option('jobsoid-location');
    }

    private function get_layout() {
        return get_option('jobsoid-layout');
    }

    private function get_theme() {
        return get_option('jobsoid-theme');
    }

    private function get_api_url() {
        $api_format = "https://%s.jobsoid.com/api/v1";
        $company_code = $this->get_company_code();
        return sprintf($api_format, $company_code);
    }

    private function get_ajax_api_url() {
        $api_format = "https://companyid.jobsoid.com/api/v1";
        return $api_format;
    }

    private function get_jobs($dept = null, $loc = null) {
        $url = $this->get_api_url() . "/jobs";
        $query = [];
        
        if (isset($_POST['job-query'])) {
            $query['q'] = htmlentities($_POST['job-query']);
        }
        
        if ($dept != null) {
            $query['dept'] = htmlentities($dept);
        }
        
        if ($loc != null) {
            $query['loc'] = htmlentities($loc);
        }
        
        $url .= '?' . http_build_query($query);
        
        $request = wp_remote_get($url);
        $response = wp_remote_retrieve_body($request);
        $output = json_decode($response);
        return $this->process_api_output($output);
    }

    private function get_departments() {
        $url = $this->get_api_url() . "/departments";
        
        if (isset($_POST['job-query'])) {
            $url = $url . '?q=' . htmlentities($_POST['job-query']);
        }
        $request = wp_remote_get($url);
        $response = wp_remote_retrieve_body($request);
        $output = json_decode($response);
        return $this->process_api_output($output);
    }

    private function get_locations() {
        $url = $this->get_api_url() . "/locations";
        
        if (isset($_POST['job-query'])) {
            $url = $url . '?q=' . htmlentities($_POST['job-query']);
        }
        
        $request = wp_remote_get($url);
        $response = wp_remote_retrieve_body($request);
        $output = json_decode($response);
        return $this->process_api_output($output);
    }

    private function get_divisions() {
        $url = $this->get_api_url() . "/divisions";
        
        if (isset($_POST['job-query'])) {
            $url = $url . '?q=' . htmlentities($_POST['job-query']);
        }
        
        $request = wp_remote_get($url);
        $response = wp_remote_retrieve_body($request);
        $output = json_decode($response);
        return $this->process_api_output($output);
    }

    private function get_job($id) {
        $url = $this->get_api_url() . '/' . 'jobs' . '/' . $id;
        $request = wp_remote_get($url);
        if ($request['response']['code'] == 404) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            get_template_part( 404 ); exit();
            
            // echo "Job id not found";
            // echo "<button >View all jobs</button>";
            // exit();
        }
        $response = wp_remote_retrieve_body($request);
        
        $output = json_decode($response);
        return (array)$output;
    }

    private function process_api_output($output) {
        $processed = array();
        if ($output) {
            foreach ($output as $key => $value) {
                array_push($processed, (array)$value);
            }
        }
        return $processed;
    }
}

$jobsoid_plugin = new JobsoidPlugin();