<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AWR DATA DEFAULT</title>
</head>
<body>
    <?php
        // --- Array lists to run
        $array_projects = ["www.fji.dk"];
        $token = "1e4a1d8dc5cf80f19b468f28f640e841";

        foreach ($array_projects as $key) {
            // - Variables
            $project_name = $key;

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

                    echo "";
                    echo "Search Engine: " . $rankings["searchengine"];
                    echo "Search Depth: " . $rankings["depth"];
                    echo "Location: " . $rankings["location"];
                    echo "Keyword: " . $rankings["keyword"];

                    $rank_data_array = $rankings["rankdata"];
                    foreach ($rank_data_array as $rank_data) {
                        echo "<br/>" . $rank_data["position"] . ". " . $rank_data["url"] . " " . $rank_data["typedescription"] . " result on page " . $rank_data["page"];
                    }
                }
            }

            
        }
    ?>
</body>
</html>