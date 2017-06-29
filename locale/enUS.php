<?php
/* used in language bar, should't be modified */
$L["english"] = "English";
$L["polish"] = "Polski";

$L["search"] = "Search";
$L["about_us"] = "About us";
$L["contact_us"] = "Contact us";
$L["privacy_policy"] = "Privacy Policy";
$L["developer_api"] = "Developer API";
$L["plug_in"] = "Plug in";

$L["video_id"] = "Video-ID";
$L["title"] = "Title";
$L["user"] = "User";
$L["channel"] = "Channel";
$L["name"] = "Name";
$L["email"] = "E-Mail";
$L["send"] = "Send";
$L["for"]			= "for";
$L["cookies"] = "Cookies";
$L["youtube_data"] = "YouTube Data";
$L["unlinked_data"] = "unlinked Data";
$L["statistics"] = "Statistics";


$L["unlinked_playground"] = "unlinked Playground";
$L["ms"] = "ms"; // milliseconds
$L["request"] = "Request";
$L["response"] = "Response";
$L["request_failed"] = "Request failed";
$L["http_request"] = "HTTP request";
$L["accepted_values"] = "accepted values";
$L["required"] = "required";
$L["bold_red"] = "bold red";
$L["execute_query"] = "Execute Query";
$L["Waiting for action"] = "Waiting for action";
$L["working"] = "Working";
$L["Data ready"] = "Data ready";
$L["Something went horribly wrong"] = "Something went horribly wrong...";

$L["recently_added"] = "Latest cached videos";
$L["no_description"] = "No description available.";
$L["added"]			 = "Added";
$L["joined"]		 = "Joined";
$L["not_found"]		 = "Not found.";
$L["no_results"]	 = "No results found.";
$L["form_error"]	 = "Form not sent. Make sure to fill all inputs and enter vaild E-Mail address";
$L["form_sent"]	 = "Form has been successfully sent.";

$L["1_view"] = "view";
$L["234_view"] = "views";
$L["n_view"] = "views";
$L["1_second_ago"] 	= "a second ago";
$L["n_second_ago"] 	= "%s seconds ago";
$L["1_minute_ago"]	= "a minute ago";
$L["234_minute_ago"]= "%s minutes ago";
$L["n_minute_ago"] 	= "%s minutes ago";
$L["1_hour_ago"] 	= "one hour ago";
$L["234_hour_ago"] 	= "%s hours ago";
$L["n_hour_ago"] 	= "%s hours ago";
$L["1_day_ago"] 	= "yesterday";
$L["234_day_ago"] 	= "%s days ago";
$L["n_day_ago"] 	= "%s days ago";
$L["1_month_ago"] 	= "a month ago";
$L["234_month_ago"] = "%s months ago";
$L["n_month_ago"] 	= "%s months ago";
$L["1_year_ago"] 	= "a year ago";
$L["234_year_ago"] 	= "%s years ago";
$L["n_year_ago"] 	= "%s years ago";
$L["past_time"]		= "t F, Y"; // php date format - a number of the day month, year

$L["234_result"]	= "results";
$L["n_result"]	= "results";
$L["1_result"]	= "result";

$L["change_sort_order"] = "Change sort order to";
$L["ascending"] = "▲ Ascending";
$L["descending"] = "▼ Descending";
$L["about"] = "About";
$L["videos"] = "Videos";

/* page translations */
$L["translators"] = "Translators";

$L["What is the purpose of \"unlinked\"?"] = "What is the purpose of \"unlinked\"?";
$L["Who the hell are you anyway?"] = "Who the hell are you anyway?";
$L["What is this sorcery?"] = "What is this sorcery?";
$L["Installing Plug in"] = "Installing Plug in";
$L["www_desc_unlinked_API_plugin"] = "unlinked API plugin";

$L["longtext_about_description_moh"] = "I run this shit. All logistic and design. Website founder.";
$L["longtext_about_description_dudeman"] = "No, I run this shit. Thanks to me you're able to be here. Hosting provider.";
$L["longtext_about_purpose_line_1"] = "I - moh - often see as my videos marked on my playlists on YouTube become unavailable due to all sort of circumstances.";
$L["longtext_about_purpose_line_2"] = "The channel of some user got banned for example. This would mark all my videos from that user as [Deleted Video] and usually give me no more information what was that.";
$L["longtext_about_purpose_line_3"] = "So I decided to make a database that would serve as a cache and JavaScript extension for my browser that would receive the information from my database - if there is any that is.";
$L["longtext_about_purpose_line_4"] = "tl;dr - cache for YouTube video and channel informations.";

$L["longtext_privacy_line_1"] = "unlinked refers to \"we\", \"I\", \"this website\". unlinked should be always written in lowercase.";
$L["longtext_privacy_line_2"] = "unlinked may store some information on your computer using Cookies. That information can contains data about your preferred language and theme for unlinked";
$L["longtext_privacy_line_3"] = "unlinked will store any video or channel related infomation provided by YouTube API. unlinked will not delete or modify stored content provided by YouTube.";
$L["longtext_privacy_line_4"] = "All data is public available through unlinked and it's API";
$L["longtext_privacy_line_5"] = "It may send YouTube video ID reported by user back to unlinked API. It may request YouTube video details froum unlinked API.";

$L["longtext_plugin_line_1"] = "unlinked API plugin <s>allows you to obtain information about deleted videos from YouTube such as title or description, it's still better than [Deleted Video] in your playlists, don't you think so?</s> - I lied. I am too lazy to write client-side UI.";
$L["longtext_plugin_line_2"] = "And by the way - it also allows you to mark currently watching video to be cached on unlinked. Since unlinked database obtains data only in this way, it require user action to obtain the data. You don't mark - you have no data in future. Keep that in mind.";
$L["longtext_plugin_line_3"] = "It's written as user JavaScript - that means it should run in any browser that is capable of injecting your code into website";
$L["longtext_plugin_line_4"] = "For Blink based browsers (Chrome, Opera Next and many others) use <a href=\"http://tampermonkey.net/\">Tampermonkey</a> extension.";
$L["longtext_plugin_line_5"] = "For FireFox use <a href=\"http://www.greasespot.net/\">Greasemonkey</a> extension.";
$L["longtext_plugin_line_6"] = "For any other browser refer to your browser homepage help";

$L["longtext_api_line_1"] = "YouTube <span class=\"parm\">videoID</span>, the <span class=\"italic\">v</span> parameter in the URL, also known as the <span class=\"italic\">id</span> parameter in the YouTube video API";
$L["longtext_api_line_2"] = "YouTube <span class=\"parm\">userID</span> - case sensitive, part of the user profile URL. For example <pre>http://youtube.com/user/<span class=\"parm\">userID</span></pre> It may be different from the displayed name in channel. This is also the <span class=\"italic\">forUsername</span> parameter in the YouTube channel API";
$L["longtext_api_line_3"] = "YouTube <span class=\"parm\">channelID</span>, the <span class=\"italic\">id</span> parameter in the YouTube channel API";
$L["longtext_api_line_4"] = "YouTube <span class=\"parm\">channelID</span>, the <span class=\"italic\">id</span> parameter in the YouTube channel API";
$L["longtext_api_line_5"] = "user uploads page that you would like fetch to, default is 1";
$L["longtext_api_line_6"] = "User specific <span class=\"parm\">function</span>, allows you to make cross-domain requests using JSONP";
?>