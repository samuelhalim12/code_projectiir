<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Evaluasi</title>
    <style>
    table,
    tr,
    td {
      padding: 10px;
      text-align: center;
      font-family: "Arial";
    }

    table {
      width: 100%;
    }

    #head {
      background-color: #a3c8ff;
    }

    #gray {
      background-color: #dfdfdf;
    }

    #white {
      background-color: white;
    }

    li {
      display: inline-block;
      padding: 5px;
    }

    .navbarColor {
      background: #FFFDD0;
    }
  </style>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-responsive.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/sl-slide.css">
</head>

<body>
    <header class="navbar navbar-fixed-top navbarColor">
        <div class="navbar-inner navbarColor">
            <div class="container navbarColor" style="width: 100%;">              
                <div class="nav-collapse collapse pull-right" style="padding-right: 8%;">
                    <ul class="nav">
                        <li><a href="home.php">Home</a></li>
                        <li class="active"><a href="evaluasi.php">Evaluasi</a></li>
                    </ul>        
                </div>
            </div>
        </div>
    </header>
    <div class="container">
        <div class="left gap">
            <h2>Evaluasi Analisis Sentimen</h2>

    <?php
    $mysqli = new mysqli("localhost", "root", "", "db_uas_iir");
    ini_set('memory_limit', '256M');
    ini_set('max_execution_time', '300');
    
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli->connect_error;
        die(); //perintah utk mengehentikan (klo error)
    }
    // Menggunakan library yang dibutuhkan
    use Phpml\FeatureExtraction\TokenCountVectorizer;
    use Phpml\Tokenization\WhitespaceTokenizer;
    use Phpml\FeatureExtraction\TfIdfTransformer;
    use Phpml\Classification\KnearestNeighbors;
    use Phpml\Math\Distance\Jaccard;
    use Phpml\Math\Distance\Cosine;
    use Phpml\Math\Distance\Overlap;
    use Phpml\CrossValidation\StratifiedRandomSplit;
    use Phpml\Dataset\ArrayDataset;

    // Mengambil library yang dibutuhkan
    include_once('simple_html_dom.php');
    require_once __DIR__ . '/vendor/autoload.php';
    // menciptakan object stemmer dan stopword
    $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
    $stemmer = $stemmerFactory->createStemmer();

    $stopwordFactory = new \Sastrawi\StopWordRemover\StopWordRemoverFactory();
    $stopword = $stopwordFactory->createStopWordRemover();

    // Variable yang dibutuhkan
    $samplee = array();
    $targets = array();
    $countNeg = 0;
    $countNeut = 0;
    $countPos = 0;
    $countValidOverlap = 0;
    $countInvalidOverlap = 0;
    $countValidJaccard = 0;
    $countInvalidJaccard = 0;
    $countValidCosine = 0;
    $countInvalidCosine = 0;
    $countValidEuclidean = 0;
    $countInvalidEuclidean = 0;
    $arrTrainCount = 0;
    $arrTestCount = 0;
    $samplee2 = array();
    $targets2 = array();
    $arrTerms = array();
    $sample_data_stopped = array();
    $testStopped = [];
    $testStoppedFixed = [];
    $tf_binary = [];
    $arr_result = array();
    $arr_resultJaccard = array();
    $arr_resultCosine = array();
    $arr_resultEuclidean = array();
    $dataSentiment = array();
    $dataSentimentKata = array();
    $i = 0;
    $countss = 1;
    // Mengambil semua data dari tabel tweets
    $sql_select = "SELECT * FROM tweets";
    $result = mysqli_query($mysqli, $sql_select);
    
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $targets[] = $row['isPositive'];
            $stemContentTrain = $stemmer->stem($row['content']);
            $stopContentTrain = $stopword->remove($stemContentTrain);
            $samplee[] = $stopContentTrain;
        }
        // menyimpan fitur dan target ke dalam array dataset $dataset
        $dataset = new ArrayDataset(
            $samplee,
            $targets
        );
        // membagi data menjadi 80% training dan 20% testing
        $split = new StratifiedRandomSplit($dataset, 0.2);

        $trainSample = $split->getTrainSamples();
        $trainLabel = $split->getTrainLabels();

        $testSampleBefore = $split->getTestSamples();
        $testLabelBefore = $split->getTestLabels();

        $countTrainLabel = count($trainLabel);
        $countTestLabel = count($testLabelBefore);
        
        foreach ($trainSample as $x => $x_value) {
            $samplee2[] = $x_value;
        }
        foreach ($testSampleBefore as $x => $x_value) {
            $samplee2[] = $x_value;
        }
        foreach ($trainLabel as $x => $x_value) {
            $targets2[] = $x_value;
        }
        foreach ($testLabelBefore as $x => $x_value) {
            $targets2[] = $x_value;
        }
        // print_r($samplee2);  

        $tf = new TokenCountVectorizer(new WhitespaceTokenizer());
        $tf->fit($trainSample);
        $tf->transform($samplee2);

        $tfidf = new TfIdfTransformer($samplee2);
        $tfidf->transform($samplee2);
        $countAll = $countTestLabel + $countTrainLabel;

        for ($z = $countAll - 1; $z >= $countAll - $countTestLabel; $z--) {
            $testStoppedFixed[] = $samplee2[$z];
        
            unset($samplee2[$z]);
        }

        $distance = new Overlap();
        $kValue = floor($countTrainLabel / 3);

        $classifier = new KNearestNeighbors($kValue, $distance);
        $classifier->train($samplee2, $trainLabel);

        foreach ($testStoppedFixed as $ters) {
            $result = $classifier->predict($ters);
            if ($result == '1.0') {
                $result = 'Positif';
            } else if ($result == '0.5') {
                $result = 'Netral';
            } else {
                $result = 'Negatif';
            }
            $arr_result[] = $result;
        }
        echo "<h3><b>KNN dengan Overlap</b></h3>";
        echo "<table border='1'>";
        echo "<tr id='head'>
                  <th>Tweets</th>
                  <th>Sentimen Original</th>
                  <th>Sentimen Sistem</th>
                  <th>Valid</th>
                  </tr>";
        $countss = 1;
        $j = 0;

        for ($i = 0; $i < $countTestLabel; $i++) {
            if ($countss % 2 == 0) {
                echo "<tr id = 'gray'>";
            } else {
                echo "<tr id = 'white'>";
            }
            $countss++;
            // echo $i;
            echo "<td>";
            echo $testSampleBefore[$i];
            echo "</td>";
            echo "<td>";
            if ($testLabelBefore[$i] == '1.0') {
                $isPos = 'Positif';
            } else if ($testLabelBefore[$i] == '0.5') {
                $isPos = 'Netral';
            } else {
                $isPos = 'Negatif';
            }
            echo $isPos;
            echo "</td>";
            echo "<td>";
            echo $arr_result[$i];
            echo "</td>";
            echo "<td>";
            if($arr_result[$i] == $isPos) {
                echo "V";
                $countValidOverlap++;
            } else {
                echo "X";
                $countInvalidOverlap++;
            }
            
            echo "</td>";
            echo "</tr>";

            $j++;
        }
        echo "</table>";
        echo "<br>";
        echo "<h4>Jumlah Data Testing = ".$countTestLabel."</h4>";
        echo "<h4>Jumlah Valid = ".$countValidOverlap."</h4>";
        $accuracy = round((($countValidOverlap / ($countValidOverlap + $countInvalidOverlap)) * 100),2);
        echo "<h4>Akurasi = ".$accuracy." %</h4>";
        echo "<br>";

        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
        $distance = new Jaccard();
        $kValue = floor($countTrainLabel / 3);

        $classifier = new KNearestNeighbors($kValue, $distance);
        $classifier->train($samplee2, $trainLabel);

        foreach ($testStoppedFixed as $ters) {
            $result = $classifier->predict($ters);
            if ($result == '1.0') {
                $result = 'Positif';
            } else if ($result == '0.5') {
                $result = 'Netral';
            } else {
                $result = 'Negatif';
            }
            // echo "The sentiment for new document is " . $result . "<br>";
            $arr_resultJaccard[] = $result;
        }
        echo "<h3><b>KNN dengan Jaccard</b></h3>";
        echo "<table border='1'>";
        echo "<tr id='head'>
                  <th>Tweets</th>
                  <th>Sentimen Original</th>
                  <th>Sentimen Sistem</th>
                  <th>Valid</th>
                  </tr>";
        $countss = 1;
        $j = 0;
        for ($i = 0; $i < $countTestLabel; $i++) {
            if ($countss % 2 == 0) {
                echo "<tr id = 'gray'>";
            } else {
                echo "<tr id = 'white'>";
            }
            $countss++;
            // echo $i;
            echo "<td>";
            echo $testSampleBefore[$i];
            echo "</td>";
            echo "<td>";
            if ($testLabelBefore[$i] == '1.0') {
                $isPos = 'Positif';
            } else if ($testLabelBefore[$i] == '0.5') {
                $isPos = 'Netral';
            } else {
                $isPos = 'Negatif';
            }
            echo $isPos;
            echo "</td>";
            echo "<td>";
            echo $arr_resultJaccard[$i];
            echo "</td>";
            echo "<td>";
            if($arr_resultJaccard[$i] == $isPos) {
                echo "V";
                $countValidJaccard++;
            } else {
                echo "X";
                $countInvalidJaccard++;
            }
            
            echo "</td>";
            echo "</tr>";

            $j++;
        }
        echo "</table>";
        echo "<br>";
        echo "<h4>Jumlah Data Testing = ".$countTestLabel."</h4>";
        echo "<h4>Jumlah Valid = ".$countValidJaccard."</h4>";
        $accuracy = round((($countValidJaccard / ($countValidJaccard + $countInvalidJaccard)) * 100),2);
        echo "<h4>Akurasi = ".$accuracy." %</h4>";
        echo "<br>";

        /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        $distance = new Cosine();
        $kValue = floor($countTrainLabel / 3);

        $classifier = new KNearestNeighbors($kValue, $distance);
        $classifier->train($samplee2, $trainLabel);

        foreach ($testStoppedFixed as $ters) {
            $result = $classifier->predict($ters);
            if ($result == '1.0') {
                $result = 'Positif';
            } else if ($result == '0.5') {
                $result = 'Netral';
            } else {
                $result = 'Negatif';
            }
            $arr_resultCosine[] = $result;
        }
        echo "<h3><b>KNN dengan Cosine</b></h3>";
        echo "<table border='1'>";
        echo "<tr id='head'>
                  <th>Tweets</th>
                  <th>Sentimen Original</th>
                  <th>Sentimen Sistem</th>
                  <th>Valid</th>
                  </tr>";
        $countss = 1;
        $j = 0;
        for ($i = 0; $i < $countTestLabel; $i++) {
            if ($countss % 2 == 0) {
                echo "<tr id = 'gray'>";
            } else {
                echo "<tr id = 'white'>";
            }
            $countss++;
            // echo $i;
            echo "<td>";
            echo $testSampleBefore[$i];
            echo "</td>";
            echo "<td>";
            if ($testLabelBefore[$i] == '1.0') {
                $isPos = 'Positif';
            } else if ($testLabelBefore[$i] == '0.5') {
                $isPos = 'Netral';
            } else {
                $isPos = 'Negatif';
            }
            echo $isPos;
            echo "</td>";
            echo "<td>";
            echo $arr_resultCosine[$i];
            echo "</td>";
            echo "<td>";
            if($arr_resultCosine[$i] == $isPos) {
                echo "V";
                $countValidCosine++;
            } else {
                echo "X";
                $countInvalidCosine++;
            }
            
            echo "</td>";
            echo "</tr>";

            $j++;
        }
        echo "</table>";
        echo "<br>";
        echo "<h4>Jumlah Data Testing = ".$countTestLabel."</h4>";
        echo "<h4>Jumlah Valid = ".$countValidCosine."</h4>";
        $accuracy = round((($countValidCosine / ($countValidCosine + $countInvalidCosine)) * 100),2);
        echo "<h4>Akurasi = ".$accuracy." %</h4>";
        echo "<br>";
    }
    ?>
        </div>
    </div>
    
</body>

</html>