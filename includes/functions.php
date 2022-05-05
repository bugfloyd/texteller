<?php

namespace Texteller;

use Exception;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberToCarrierMapper;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumberUtil;
use WP_Error;
use WP_User;

defined("ABSPATH") || exit();

function tlr_get_number_info(string $mobile): array
{
    $data = [];
    $number_types = [
        0 => "Fixed Line",
        1 => "Mobile",
        2 => "Fixed Line or Mobile",
        3 => "Toll Free",
        4 => "Premium Rate",
        5 => "Shared Cost",
        6 => "VOIP",
        7 => "Personal Number",
        8 => "Pager",
        9 => "UAN",
        10 => "Unknown",
        27 => "Emergency",
        28 => "Voice Mail",
        29 => "Short Code",
    ];

    $phone_util = PhoneNumberUtil::getInstance();
    try {
        $number_proto = $phone_util->parse($mobile);
        $data["intl_number"] = $phone_util->format(
            $number_proto,
            PhoneNumberFormat::INTERNATIONAL
        );
        $data["national_number"] = $phone_util->format(
            $number_proto,
            PhoneNumberFormat::NATIONAL
        );
        $data["region_code"] = $phone_util->getRegionCodeForNumber(
            $number_proto
        );
        $data["country_code"] = $phone_util->getCountryCodeForRegion(
            $data["region_code"]
        );
        $data["type"] = isset(
            $number_types[$phone_util->getNumberType($number_proto)]
        )
            ? $number_types[$phone_util->getNumberType($number_proto)]
            : $number_types[10];

        $geo_coder = PhoneNumberOfflineGeocoder::getInstance();
        $data["area_desc"] = $geo_coder->getDescriptionForNumber(
            $number_proto,
            "en_US"
        ); //todo translate and change locale

        $carrier_mapper = PhoneNumberToCarrierMapper::getInstance();
        $data["carrier"] = $carrier_mapper->getNameForNumber(
            $number_proto,
            "en"
        ); //todo translate and change locale

        $timeZoneMapper = PhoneNumberToTimeZonesMapper::getInstance();
        $data["time_zones"] = $timeZoneMapper->getTimeZonesForNumber(
            $number_proto
        );
    } catch (NumberParseException $e) {
        //todo: write log
    }
    return $data;
}

function tlr_convert_numbers(string $text, string $dest_lang = null): string
{
    $english = range(0, 9);
    $devanagari = ["०", "१", "२", "३", "४", "५", "६", "७", "८", "९"];
    $arabic = ["٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩"];
    $persian = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
    $bengali = ["০", "১", "২", "৩", "৪", "৫", "৬", "৭", "৮", "৯"];
    $thai = ["๐", "๑", "๒", "๓", "๔", "๕", "๖", "๗", "๘", "๙"];
    $chinese_simple = [
        "〇",
        "一",
        "二",
        "三",
        "四",
        "五",
        "六",
        "七",
        "八",
        "九",
    ];
    $chinese_complex = [
        "零",
        "壹",
        "貳",
        "參",
        "肆",
        "伍",
        "陸",
        "柒",
        "捌",
        "玖",
    ];

    $reg_ex =
        "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
    $links = [];

    if (preg_match_all($reg_ex, $text, $links)) {
        $replacement = "TXTEL_LinkI";
        $save_links = [];
        foreach ($links[0] as $link) {
            $text = str_replace($link, $replacement, $text);
            $save_links[$replacement] = $link;
            $replacement = $replacement . "I";
        }
    }

    switch ($dest_lang) {
        case "none":
            break;
        case "devanagari":
            $text = str_replace($english, $devanagari, $text);
            break;
        case "persian":
            $text = str_replace($english, $persian, $text);
            break;
        case "arabic":
            $text = str_replace($english, $arabic, $text);
            break;
        case "bengali":
            $text = str_replace($english, $bengali, $text);
            break;
        case "thai":
            $text = str_replace($english, $thai, $text);
            break;
        case "chinese-simple":
            $text = str_replace($english, $chinese_simple, $text);
            break;
        case "chinese-complex":
            $text = str_replace($english, $chinese_complex, $text);
            break;
    }

    if (isset($save_links)) {
        $text = str_replace(
            array_keys($save_links),
            array_values($save_links),
            $text
        );
    }
    return $text;
}

function tlr_sanitize_input($field_value, $field_id = null, $mode = "user_reg")
{
    switch ($field_id) {
        case "id":
        case "user_id":
            return absint($field_value);
        case "mobile":
            return tlr_sanitize_mobile($field_value);
        case "email":
            return sanitize_email($field_value);
        case "member_group":
            $field_value = !is_array($field_value)
                ? (array) $field_value
                : $field_value;
            $member_groups = [];

            foreach ($field_value as $key => $member_group) {
                $member_group = sanitize_text_field(wp_unslash($member_group));

                if ("admin_edit" === $mode && $member_group > 0) {
                    $term = get_term_by(
                        "id",
                        (int) $member_group,
                        "member_group"
                    );
                } else {
                    $term = get_term_by("slug", $member_group, "member_group");
                }

                if ($term && $term->term_id > 0) {
                    $member_groups[] = $term->slug;
                }
            }
            return $member_groups;
        case "gateway":
            $active_gateways = (array) get_option("tlr_active_gateways", []);
            return in_array($field_value, $active_gateways, true)
                ? $field_value
                : "";
        case "first_name":
        case "last_name":
        case "title":
        default:
            return sanitize_text_field($field_value);
    }
}

function tlr_sanitize_mobile($mobile)
{
    $mobile = sanitize_text_field($mobile);
    $mobile = tlr_convert_numbers($mobile, "en");
    return preg_replace("/[^0-9+]/", "", $mobile);
}

function tlr_is_mobile_valid($mobile): ?bool
{
    $phoneUtil = PhoneNumberUtil::getInstance();
    try {
        $NumberProto = $phoneUtil->parse($mobile);
        return $phoneUtil->isValidNumber($NumberProto);
    } catch (NumberParseException $e) {
        return null;
    }
}

function tlr_get_full_number(string $mobile, string $default_region = "")
{
    $phoneUtil = PhoneNumberUtil::getInstance();
    try {
        $NumberProto = $phoneUtil->parse($mobile, $default_region);

        if ($phoneUtil->isValidNumber($NumberProto)) {
            return $phoneUtil->format($NumberProto, PhoneNumberFormat::E164);
        } else {
            return false;
        }
    } catch (NumberParseException $e) {
        return null;
    }
}

function tlr_is_field_value_valid(string $field_id, $field_value): bool
{
    $special_chars = "/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]/";
    $numbers = "/[۰۱۲۳۴۵۶۷۸۹٩٨٧٦٥٤٣٢١٠0123456789]/u";

    switch ($field_id) {
        case "mobile":
            return (bool) tlr_is_mobile_valid($field_value);
        case "last_name":
        case "first_name":
            return mb_strlen($field_value, "UTF-8") > 1 &&
                !preg_match($special_chars, $field_value) &&
                !preg_match($numbers, $field_value);
        case "email":
            return (bool) is_email($field_value);
        case "title":
            return in_array($field_value, ["mr", "mrs", "miss", "ms"]);
        case "member_group":
            foreach ((array) $field_value as $member_group) {
                $term = get_term_by("slug", $member_group, "member_group");
                if (!$term) {
                    return false;
                }
            }
            return true;
        default:
            return false;
    }
}

/**
 * @param $field_id
 * @param $field_data
 * @param $field_value
 *
 * @return int 10: validated | -1: error in validation | 0: empty required field | 1: empty optional field
 */
function tlr_validate_field($field_id, $field_data, $field_value): int
{
    //check if field is required
    if (isset($field_data["required"]) && $field_data["required"] == "1") {
        if (empty($field_value)) {
            return 0; // empty required field
        }
    } elseif (empty($field_value)) {
        return 1; // empty optional field
    }

    return tlr_is_field_value_valid($field_id, $field_value)
            ? 10 /* valid */
            : -1 /* not valid */;
}

/**
 * @param string $member_mid
 * @param string $field one of these: member_id | post_id | user_id | mobile | email
 *
 * @return bool
 */
function tlr_member_exists(string $member_mid, string $field = "mobile"): bool
{
    $member_id = tlr_get_member_id($member_mid, $field);
    return $member_id > 0;
}

function tlr_get_member_id($mid, $field = "mobile")
{
    if (!in_array($field, ["ID", "user_id", "mobile", "email"])) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . "tlr_members";
    $where_type = "mobile" === $field || "email" === $field ? "%s" : "%d";

    $member_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM $table_name WHERE $field = $where_type",
            $mid
        )
    );

    return (int) $member_id;
}

function tlr_get_user_id($mid, $field = "ID"): int
{
    //todo: sanitize $field
    global $wpdb;
    $table_name = $wpdb->prefix . "tlr_members";
    $where_type = "mobile" === $field || "email" === $field ? "%s" : "%d";

    $user_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT user_id FROM $table_name WHERE $field = $where_type",
            $mid
        )
    );

    return (int) $user_id;
}

function tlr_get_user_by_mobile($mobile_number)
{
    $user_id = tlr_get_user_id($mobile_number, "mobile");
    return $user_id ? get_userdata($user_id) : false;
}

/**
 * @param $maybe_mobile
 * @param $default_countries
 *
 * @return WP_User | bool
 */
function tlr_get_possible_user($maybe_mobile, $default_countries)
{
    if (tlr_is_mobile_valid($maybe_mobile)) {
        $mobile = $maybe_mobile;
    } else {
        foreach ($default_countries as $default_country) {
            $mobile = tlr_get_full_number($maybe_mobile, $default_country);
            if ($mobile) {
                break;
            }
        }
    }

    if (!empty($mobile)) {
        return tlr_get_user_by_mobile($mobile);
    } else {
        return false;
    }
}

function tlr_get_mobile(string $mid, string $field = "ID"): string
{
    // todo : check fields for valid values
    global $wpdb;
    $table_name = $wpdb->prefix . "tlr_members";
    $where_type = "mobile" === $field || "email" === $field ? "%s" : "%d";

    $mobile = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT mobile FROM $table_name WHERE $field = $where_type",
            $mid
        )
    );
    return !$mobile ? "" : $mobile;
}

function tlr_member_query($search): array
{
    global $wpdb;
    $search = ltrim($search, "0");
    $table_name = $wpdb->prefix . "tlr_members";

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID FROM $table_name WHERE ( first_name like %s) OR ( last_name like %s) OR ( mobile like %s) OR ( user_id = %d) OR ( ID = %d)",
            ["%$search%", "%$search%", "%$search%", $search, $search]
        ),
        ARRAY_A
    );

    return wp_list_pluck($results, "ID");
}

function tlr_write_log($log)
{
    if (defined("WP_DEBUG") && true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }
}

function tlr_encrypt($data, $key = null)
{
    if (empty($data)) {
        return $data;
    }
    if (is_null($key)) {
        $key = substr(NONCE_KEY, 0, 8) . substr(NONCE_KEY, -8);
    }
    // Remove the base64 encoding from our key
    //$encryption_key = base64_decode($key);
    // Generate an initialization vector
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("aes-256-cbc"));
    // Encrypt the data using AES 256 encryption in CBC mode using our encryption key and initialization vector.
    $encrypted = openssl_encrypt($data, "aes-256-cbc", $key, 0, $iv);
    // The $iv is just as important as the key for decrypting, so save it with our encrypted data using a unique separator (::)
    return base64_encode($encrypted . "::" . $iv);
}

function tlr_decrypt($data, $key = null)
{
    if (empty($data)) {
        return $data;
    }
    if (is_null($key)) {
        $key = substr(NONCE_KEY, 0, 8) . substr(NONCE_KEY, -8);
    }
    // Remove the base64 encoding from our key
    //$encryption_key = base64_decode($key);
    // To decrypt, split the encrypted data from our IV - our unique separator used was "::"
    list($encrypted_data, $iv) = explode("::", base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, "aes-256-cbc", $key, 0, $iv);
}

function tlr_generate_code(): int
{
    try {
        $code = random_int(10000, 99999);
    } catch (Exception $e) {
        tlr_write_log($e->getMessage());
        $code = rand(10000, 99999);
    }
    return $code;
}

/**
 * Get data if set, otherwise return a default value or null. Prevent notices when data is not set.
 *
 * @param  mixed  $var     Variable.
 * @param string|null $default Default value.
 *
 * @return mixed
 */
function tlr_get_var($var, string $default = null)
{
    return $var ?? $default;
}

function tlr_strtolower($string)
{
    return function_exists("mb_strtolower")
        ? mb_strtolower($string)
        : strtolower($string);
}

function tlr_get_posted_field_value(
    $field_id,
    $member = null,
    $custom_name = ""
) {
    $getter_method = "get_$field_id";
    $stored_value =
        !is_null($member) && method_exists($member, $getter_method)
            ? $member->$getter_method()
            : "";

    switch ($field_id) {
        case "first_name":
        case "last_name":
        case "member_group":
        case "title":
        case "mobile":
        case "email":
            if ($custom_name) {
                $posted_value = !empty($_POST[$custom_name])
                    ? esc_attr(wp_unslash($_POST[$custom_name]))
                    : "";
            } else {
                $posted_value = !empty($_POST["tlr_$field_id"])
                    ? esc_attr(wp_unslash($_POST["tlr_$field_id"]))
                    : "";
            }
            break;
        case "description":
            if ($custom_name) {
                $posted_value = !empty($_POST[$custom_name])
                    ? esc_textarea(wp_unslash($_POST[$custom_name]))
                    : "";
            } else {
                $posted_value = !empty($_POST["tlr_$field_id"])
                    ? esc_textarea(wp_unslash($_POST["tlr_$field_id"]))
                    : "";
            }
            break;
    }

    if (!empty($posted_value)) {
        return $posted_value;
    } else {
        return $stored_value;
    }
}

function tlr_get_public_member_groups(): array
{
    $member_group = get_terms([
        "taxonomy" => "member_group",
        "hide_empty" => false,
        "meta_key" => "tlr_is_public",
        "meta_value" => 1,
    ]);

    return !is_wp_error($member_group)
        ? wp_list_pluck($member_group, "name", "slug")
        : [];
}

function tlr_get_member_groups(): array
{
    $member_group = get_terms([
        "taxonomy" => "member_group",
        "hide_empty" => false,
    ]);

    return !is_wp_error($member_group)
        ? wp_list_pluck($member_group, "name", "slug")
        : [];
}

function tlr_add_notice(
    WP_Error &$errors,
    $error_code,
    $error_msg,
    $notice_title = true
) {
    if ($notice_title) {
        $errors->add(
            $error_code,
            sprintf(
                "<strong>%s</strong>: %s",
                __("ERROR", "texteller"),
                $error_msg
            )
        );
    } else {
        $errors->add($error_code, $error_msg);
    }
}

function tlr_get_base_tags_values_array(string $base_tag_id, $object): array
{
    $base_tags_values = [];

    if ("member" === $base_tag_id) {
        /* @var Member $object */

        $statuses = [
            "canceled" => _x(
                "canceled",
                "replacement text for the {status} tag",
                "texteller"
            ),
            "registered" => _x(
                "registered",
                "replacement text for the {status} tag",
                "texteller"
            ),
            "verified" => _x(
                "verified",
                "replacement text for the {status} tag",
                "texteller"
            ),
        ];

        // set status for draft members (not-saved yet)
        $member_status = $object->get_status();
        $status =
            !empty($member_status) && isset($statuses[$member_status])
                ? $statuses[$member_status]
                : "";

        switch ($object->get_title()) {
            case "Mrs":
                $title = _x(
                    "Mrs",
                    "replacement text for the {title} tag",
                    "texteller"
                );
                break;
            case "miss":
                $title = _x(
                    "Miss",
                    "replacement text for the {title} tag",
                    "texteller"
                );
                break;
            case "ms":
                $title = _x(
                    "Ms",
                    "replacement text for the {title} tag",
                    "texteller"
                );
                break;
            case "mr":
                $title = _x(
                    "Mr",
                    "replacement text for the {title} tag",
                    "texteller"
                );
                break;
            default:
                $title = "";
        }

        $member_group_text = [];
        $member_groups = $object->get_member_group();
        foreach ($member_groups as $member_group) {
            $term = get_term_by("slug", $member_group, "member_group");
            if (is_object($term)) {
                $member_group_text[] = $term->name;
            }
        }
        $member_group_text = implode(", ", $member_group_text);

        $user_id = $object->get_user_id();
        $user = get_user_by("id", $user_id);
        $username = $user ? $user->user_login : "";

        $base_tags_values = [
            "member_first_name" => !empty($object->get_first_name())
                ? $object->get_first_name()
                : "",
            "member_last_name" => !empty($object->get_last_name())
                ? $object->get_last_name()
                : "",
            "member_full_name" => !empty($object->get_name())
                ? $object->get_name()
                : "",
            "member_reg_date" => str_replace(
                "-",
                "/",
                $object->get_registered_date()
            ), //todo: settings to choose date time format
            "member_title" => $title,
            "member_member_group" => $member_group_text,
            "member_status" => $status,
            "member_email" => !empty($object->get_email())
                ? $object->get_email()
                : "",
            "member_mobile" => $object->get_mobile(),
            "member_id" => $object->get_id(),
            "member_username" => $username,
            "member_user_id" => $user_id,
        ];
    }

    return $base_tags_values;
}

function tlr_sanitize_text_field_array(array $values): array
{
    return array_map(function ($value) {
        return sanitize_text_field($value);
    }, $values);
}

/**
 * Tries to get the class name the selected gateway
 * Writes tlr_log on failure
 *
 * @param string $gateway
 *
 * @return bool|string $gateway_instance
 */
function tlr_get_gateway_class(string $gateway)
{
    if (empty($gateway)) {
        return false;
    }
    $uc_gateway = ucfirst($gateway);
    $gateway_class = "Texteller\Gateways\\$uc_gateway\Base";

    /**
     * Filters gateway PHP class name
     *
     * @since 0.1.3
     * @param string $gateway_class Class name to be filtered
     * @param string $gateway       Gateway slug
     */
    $gateway_class = apply_filters(
        "tlr_gateway_class_name",
        $gateway_class,
        $gateway
    );

    if (class_exists($gateway_class)) {
        return $gateway_class;
    } else {
        tlr_write_log("Gateway not found: $gateway_class");
    }
    return false;
}

function get_notification_triggers()
{
    global $wp_settings_fields;

    $base_triggers = [
        "tlr_manual_send" => _x(
            "Texteller: Admin Manual Send",
            "message trigger",
            "texteller"
        ),
        "tlr_manual_send_member" => _x(
            "Texteller: Member Manual Send",
            "message trigger",
            "texteller"
        ),
        "tlr_inbound_message" => _x(
            "Inbound Message",
            "message trigger",
            "texteller"
        ),
    ];

    $base_triggers = apply_filters("tlr_message_triggers", $base_triggers);

    if (empty($wp_settings_fields)) {
        return false;
    }
    $notification_triggers = [];
    foreach ($wp_settings_fields as $page_slug => $page_options) {
        if (0 !== strpos($page_slug, "tlr_")) {
            continue;
        }
        foreach ($page_options as $section_options) {
            foreach ($section_options as $option_id => $option_data) {
                if (
                    !isset($option_data["args"]["type"]) ||
                    "notification" !== $option_data["args"]["type"]
                ) {
                    continue;
                }
                if (0 === strpos($option_id, "tlr_trigger_")) {
                    $trigger_slug = str_replace("tlr_trigger_", "", $option_id);
                } else {
                    $trigger_slug = $option_id;
                }
                $notification_triggers[$trigger_slug] =
                    $option_data["args"]["title"];
            }
        }
    }
    return array_merge($base_triggers, $notification_triggers);
}

function get_page_sections(string $page): array
{
    global $wp_settings_sections;

    if (isset($wp_settings_sections[$page])) {
        $sections = [];
        foreach (
            $wp_settings_sections[$page]
            as $section_slug => $section_data
        ) {
            $sections[$section_slug] = $section_data["title"];
        }
        return $sections;
    }
    return [];
}

function get_form_fields(string $option_name, string $option_class): array
{
    $fields = [];

    if (
        class_exists($option_class) &&
        method_exists($option_class, "get_default_fields")
    ) {
        $defaults = $option_class::get_default_fields();
        $stored_fields = get_option($option_name, []);

        if (!empty($stored_fields)) {
            $field_slugs = array_keys(
                array_merge(array_flip(array_keys($stored_fields)), $defaults)
            );
        } else {
            $field_slugs = array_keys($defaults);
        }

        foreach ($field_slugs as $field_slug) {
            // If field data does not exist in the stored option, use the defaults
            $fields[$field_slug] = !empty($stored_fields[$field_slug])
                ? $stored_fields[$field_slug]
                : $defaults[$field_slug];

            // If we don't have a stored title, use default value
            $fields[$field_slug]["title"] = !empty(
                $stored_fields[$field_slug]["title"]
            )
                ? $stored_fields[$field_slug]["title"]
                : $defaults[$field_slug]["title"];

            // If $has_size_field, but we don't have a stored size, use default value
            if (
                empty($stored_fields[$field_slug]["size"]) &&
                !empty($defaults[$field_slug]["size"])
            ) {
                $fields[$field_slug]["size"] = $defaults[$field_slug]["size"];
            }
        }
    }

    return $fields;
}

/**
 * Include the desired template in admin area
 *
 * @param string $template Template slug to be loaded
 *
 * @since 0.1.3
 */
function get_admin_template(string $template)
{
    $file = TLR_INC_PATH . "/admin/templates/$template.php";
    if (file_exists($file)) {
        include $file;
    }
}

/**
 * @since 0.1.3
 * @param array $array
 * @return int|string|null
 */
function array_key_first(array $array)
{
    foreach ($array as $key => $unused) {
        return $key;
    }
    return null;
}
