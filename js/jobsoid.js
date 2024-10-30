var JobsoidAdmin = (function ($) {
    var $settings_form, $btn_connect, $btn_disconnect, $company_code;
    var $connect_status_msg, $dropdowns;
    var $page_theme_options;
    var $btn_copy_shortcode, $shortcode_text, $shortcode_group_by, $shortcode_layout, $shortcode_department, $shortcode_location;

    var init = function () {
        $dropdowns              = $('.jobsoid_select2');

        $btn_connect            = $("#jobsoid_connect")
        $btn_disconnect         = $("#jobsoid_disconnect")
        $company_code           = $("#jobsoid_company_code");
        $settings_form          = $("#jobsoid_settings_form");
        $connect_status_msg     = $("#jobsoid_connect_status_msg");
        $btn_copy_shortcode     = $("#jobsoid_copy_shortcode");

        $page_theme_options     = $('.jobsoid_page_theme');

        $shortcode_group_by     = $(".jobsoid_shortcode_group_by");
        $shortcode_layout       = $(".jobsoid_shortcode_layout");
        $shortcode_department   = $("#jobsoid_shortcode_department");
        $shortcode_location     = $("#jobsoid_shortcode_location");
        $shortcode_text         = $("#jobsoid_shortcode");

        init_dropdowns();
        init_connect();
        init_page_settings();
        init_shortcode();
    };

    var init_dropdowns = function () {
        $dropdowns.select2();
    };

    var init_connect = function () {
        $btn_disconnect.click(function () {
            $company_code.val("");
            $settings_form.submit();
        });

        $btn_connect.click(function () {
            var code = $company_code.val();

            if ($.trim(code) == "") {
                show_status("Please Enter company Code");
            } else {
                show_status('<img src="/wp-content/plugins/jobsoid/img/loader.svg">');

                url = jobsoid_ajax_object.ajax_url.replace(/companyid/, code);
                $.ajax(url)
                    .success(function () {
                        $settings_form.submit();
                    })
                    .error(function () {
                        show_status("Company Not Found");
                    });
            }

        });
    };

    var show_status = function (message) {
        $connect_status_msg.html(message);
    };

    var init_page_settings = function () {
        $page_theme_options.click(function () {
            $(this).find('input[type=radio]').prop('checked', true);
        });
    };

    var init_shortcode = function () {
        generate_shortcode();

        $shortcode_group_by.click(generate_shortcode);
        $shortcode_layout.click(generate_shortcode);
        $shortcode_department.change(generate_shortcode);
        $shortcode_location.change(generate_shortcode);

        $btn_copy_shortcode.click(function () {
            generate_shortcode();

            var copyText = document.getElementById("jobsoid_shortcode");
            var range = document.createRange();
            range.selectNodeContents(copyText);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
        });
    };

    var generate_shortcode = function () {
        var group_by = $shortcode_group_by.filter(":checked").val();
        var layout = $shortcode_layout.filter(":checked").val();
        var department = $shortcode_department.val();
        var location = $shortcode_location.val();

        var shortcode = '[jobsoid groupby="' + group_by + '" layout="' + layout + '" department="' + department + '" location="' + location + '"]';

        $shortcode_text.html(shortcode);
    };

    return {
        init: init
    };
})(jQuery);
var Jobsoid = (function ($) {
    var $reset, $filter_location, $filter_department, $job_list;

    var init = function () {
        $reset = $("#jobsoid_filter_reset");
        $filter_location = $("#jobsoid_filter_location");
        $filter_department = $("#jobsoid_filter_department");
        $job_list = $('#jobsoid-jobs');

        init_dropdowns();
        init_reset_button();
        init_filters();
    };

    var init_dropdowns = function () {
        $('.jobsoid_select2').select2();
    };

    var init_reset_button = function () {
        $reset.hide();

        $reset.click(function () {
            $reset.hide();
            reset();
            load_jobs();
        });
    };

    var init_filters = function () {
        $('.jobsoid_select2').change(function () {
            $reset.show();
            load_jobs();
        });
    };

    var reset = function () {
        $filter_location.val("").trigger('change.select2');
        $filter_department.val("").trigger('change.select2');
    };

    var load_jobs = function () {
        $.ajax({
            url: filter_ajax.ajax_url,
            type: 'post',
            data: {
                action: 'filter',
                security: filter_ajax.check_nonce,
                loc_value: $filter_location.val(),
                dept_value: $filter_department.val()
            },
            success: function (response) {
                $job_list.html(response);
            }
        });
    };

    return {
        init: init
    };
})(jQuery);