<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWR DATA TOP 50</title>
</head>
<body>
    <?php
        // --- Require DB connection
        require_once("conn.php");

        // --- Array lists to run
        $array_projects = array(
            array("www.fji.dk", "fji.dk/")
        );
        $token = "1e4a1d8dc5cf80f19b468f28f640e841";

        // --- Empty extracted_jsons
        array_map( 'unlink', array_filter((array) glob("extracted_jsons/*") ) );

        foreach ($array_projects as $key) {
            // - Variables
            $project_name = $key[0];
            $project_name_sanitized = preg_replace("/[\W_]+/u", '', $project_name);
            $project_website = $key[1];
            
            // - Check if database table already exists
            function databaseTableHandler($project_name_sanitized, $con) {
                $table_suffix = $project_name_sanitized;

                if (mysqli_query($con, "DESCRIBE `awr_data_top50_".$table_suffix."`")) {
                    // - Table exists, empty table
                    mysqli_query($con, "TRUNCATE TABLE `awr_data_top50_".$table_suffix."`");
                    echo "<script>console.log('SQL table already exists (awr_data_top50_".$table_suffix."). - table emptied')</script>";
                    return;
                } else {
                    // - Table does not exists, create it
                    $sql_create_table = "CREATE TABLE `awr_data_top50_".$table_suffix."` (
                        unique_id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        keyword VARCHAR(255) NOT NULL,
                        url VARCHAR(255) NOT NULL,
                        position INT(11) NOT NULL,
                        typedescription VARCHAR(255) NOT NULL,
                        page INT(11) NOT NULL,
                        domain VARCHAR(255) NOT NULL,
                        project_client VARCHAR(255) NOT NULL
                    )";

                    if ($con->query($sql_create_table) === TRUE) {
                        echo "<script>console.log('SQL table created successfully (awr_data_top50_".$table_suffix.").')</script>";
                    } else {
                        echo "ErrorResponse: Error creating table: " . $con->error;
                    }
                }
            }
            databaseTableHandler($project_name_sanitized, $con);

            // - Get dates
            $url_get_dates = "https://api.awrcloud.com/v2/get.php?action=get_dates&project=".$project_name."&token=".$token."";
            $response_get_dates = json_decode(file_get_contents($url_get_dates), true);
            $project_date = $response_get_dates["details"]["dates"][0]["date"];

            // - Get list [*** ARCHIVED]
            // $url_list = "https://api.awrcloud.com/v2/get.php?action=list&project=".$project_name."&date=".$project_date."&token=".$token."&compression=zip";
            // $response_list = json_decode(file_get_contents($url_list), true);

            // $url_get_ranking = "https://api.awrcloud.com/v2/get.php?action=get&project=".$project_name."&date=".$project_date."&token=".$token."&compression=zip";
            $url_get_ranking = "https://api.awrcloud.com/get.php?action=list&project=" . $project_name . "&date=" . $project_date . "&token=" . $token . "&compression=zip";
            $response_get_ranking = file_get_contents($url_get_ranking);
            
            $responseRows = explode("\n", $response_get_ranking);

            if ($responseRows[0] != "OK") {
                echo "No results for date.";
                continue;
            }

            $dateFilesCount = $responseRows[1];
            if ($dateFilesCount == 0) {
                continue;
            }

            $table = "awr_data_top50_".$project_name_sanitized;

            for ($i = 0; $i < $dateFilesCount; $i++) {
                $urlRequest = $responseRows[2 + $i];
                $urlHandle = fopen($urlRequest, 'r');

                $tempZip = fopen("tempfile.zip", "w");

                while (!feof($urlHandle))
                {
                    $readChunk = fread($urlHandle, 1024 * 8);
                    fwrite($tempZip, $readChunk);
                }
                fclose($tempZip);
                fclose($urlHandle);

                $pathToExtractedJson = "extracted_jsons/";

                $zip = new ZipArchive;
                $res = $zip->open("tempfile.zip");

                if ($res === FALSE)
                {
                    echo "Could not extract JSON files from the zip archive";
                    continue;
                }

                $zip->extractTo($pathToExtractedJson);
                $zip->close();

                $dir_handle = opendir($pathToExtractedJson);

                while (false !== ($entry = readdir($dir_handle)))
                {
                    if ($entry == ".." || $entry == ".")
                    {
                        continue;
                    }

                    // the json file contains nested json objects, make sure you use associative arrays
                    $rankings = json_decode(file_get_contents($pathToExtractedJson . $entry), true); 

                    // echo "";
                    // echo "Search Engine: " . $rankings["searchengine"];
                    // echo "Search Depth: " . $rankings["depth"];
                    // echo "Location: " . $rankings["location"];
                    // echo "Keyword: " . $rankings["keyword"];

                    $rank_data_array = $rankings["rankdata"];
                    foreach ($rank_data_array as $rank_data) {
                        // echo "<br/>" . $rank_data["position"] . ". " . $rank_data["url"] . " " . $rank_data["typedescription"] . " result on page " . $rank_data["page"];
                        if (!empty($rank_data)) {
                            $rank_data_domain = parse_url($rank_data["url"]);
                            $rank_data_domain_host = $rank_data_domain["host"];
                            $sql_insert = "INSERT INTO ".$table." (keyword, url, position, typedescription, page, domain, project_client)
                            VALUES ('".$con->real_escape_string($rankings["keyword"])."', '".$con->real_escape_string($rank_data["url"])."', '".$con->real_escape_string($rank_data["position"])."', '".$con->real_escape_string($rank_data["typedescription"])."', '".$con->real_escape_string($rank_data["page"])."', '".$con->real_escape_string($rank_data_domain_host)."', '".$con->real_escape_string($project_name)."')";

                            if ($con->query($sql_insert) === TRUE) {
                                            
                            } else {
                                echo "Error: " . $sql_insert . "<br>" . $con->error;
                            }
                        }
                    }
                }
            }

            
        }
    ?>
</body>
</html>