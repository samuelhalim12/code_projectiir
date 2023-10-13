<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>Home</title>
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

    .sizeBtn {
      width: 100px;
      height: 50px;
      font-size: 20px;
    }

    .sizeInput {
      height: 40px;
      width: 300px;
    }

    .convText {
      font-size: 16px;
      vertical-align: middle;
    }

    .navbarColor {
      background: #FFFDD0;

    }

    header {
      background-color: #FFFDD0;
    }
  </style>
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-responsive.min.css">
  <link rel="stylesheet" href="css/font-awesome.min.css">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/sl-slide.css">
</head>

<body>
  <form method="post" action="home.php">
    <header class="navbar navbar-fixed-top navbarColor">
      <div class="navbar-inner navbarColor">
        <div class="container navbarColor" style="width: 100%;">
          <div class="nav-collapse collapse pull-right navbarColor" style="padding-right: 8%;">
            <ul class="nav">
              <li class="active"><a href="home.php">Home</a></li>
              <li><a href="evaluasi.php">Evaluasi</a></li>
            </ul>
          </div>
          <div class="left gap navbarColor">

          </div>
        </div>
      </div>
    </header>
    <div class="container">
      <div class="left gap">
        <br><br>
        <h1>Crawling Tweet Dan Analisis Sentimen</h1>
        <br><br>
        <label><b>Input Keyword: </b></label>
        <div class="form-horizontal">
          <input type="text" id="keyword" name='keyword' class="sizeInput">
          <input type="submit" id="btnsearch" name="btnsearch" value="Search" class="btn-success sizeBtn">
        </div><br>
        <label><b>Pilih Metode Similaritas: </b></label>
        <label class="convText"><input type="radio" name="rmethod" id="roverlap" value="Overlap" style="vertical-align: middle; margin: 0px;" checked> Overlap</label>
        <label class="convText"><input type="radio" name="rmethod" id="rjaccard" value="Jaccard" style="vertical-align: middle; margin: 0px;"> Jaccard</label>
        <label class="convText"><input type="radio" name="rmethod" id="rcosine" value="Cosine" style="vertical-align: middle; margin: 0px;"> Cosine</label>
        <!-- <label class="convText"><input type="radio" name="rmethod" id="reuclidean" value="Euclidean" style="vertical-align: middle; margin: 0px;"> Euclidean</label> -->

        <?php
        echo "<br>";
        // Set waktu maksimum eksekusi menjadi 300 detik, apabila melebihi 300 detik muncul runtime error
        ini_set('max_execution_time', '300');
        // Set memory limit ke 256 MB untuk mengatasi error out of memory
        ini_set('memory_limit', '-1');
        // Connect ke database
        $mysqli = new mysqli("localhost", "root", "", "db_uas_iir");
        if ($mysqli->connect_errno) {
          echo "Failed to connect to MySQL: " . $mysqli->connect_error;
          die(); //perintah utk mengehentikan (klo error)
        }
        
        // menggunakan library yang dibutuhkan
        use Phpml\FeatureExtraction\TokenCountVectorizer;
        use Phpml\Tokenization\WhitespaceTokenizer;
        use Phpml\FeatureExtraction\TfIdfTransformer;
        use Phpml\Classification\KnearestNeighbors;

        // library tambahan yaitu Jaccard, Cosine, Overlap
        use Phpml\Math\Distance\Jaccard;
        use Phpml\Math\Distance\Cosine;
        use Phpml\Math\Distance\Overlap;

        if (isset($_POST['btnsearch'])) {
          // memanggil library
          include_once('simple_html_dom.php');
          require_once __DIR__ . '/vendor/autoload.php';

          // membuat object untuk stemmer dan stopword
          $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
          $stemmer = $stemmerFactory->createStemmer();

          $stopwordFactory = new \Sastrawi\StopWordRemover\StopWordRemoverFactory();
          $stopword = $stopwordFactory->createStopWordRemover();

          // membuat variabel yang dibutuhkan
          $countNeg = 0;
          $countNeut = 0;
          $countPos = 0;
          $arrTrainCount = 0;
          $arrTestCount = 0;
          $arrTerms = array();
          $sample_data_stopped = array();
          $testStopped = [];
          $arrCloneHelper = [];
          $testStoppedFixed = [];
          $tf_binary = [];
          $arr_result = array();
          $dataSentiment = array();
          $dataSentimentKata = array();
          $i = 0;

          // mengambil seluruh data dari table tweets
          $sql_select = "SELECT * FROM tweets";
          $result = mysqli_query($mysqli, $sql_select);
          if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
              // stem dan stop word dari content dan disimpan ke array sample_data_stopped
              $stemContentTrain = $stemmer->stem($row['content']);
              $stopContentTrain = $stopword->remove($stemContentTrain);
              $sample_data_stopped[] = $stopContentTrain;
              // menyimpan nilai sentiment dari setiap tweet ke dalam array dataSentiment
              $dataSentiment[] = $row["isPositive"];
              if ($row["isPositive"] == '0.0') {
                $dataSentimentKata[] = 'Negatif';
              } else if ($row["isPositive"] == '0.5') {
                $dataSentimentKata[] = 'Netral';
              } else if ($row["isPositive"] == '1.0') {
                $dataSentimentKata[] = 'Positif';
              }
            }
          }
          // mereplace spasi dengan karakter "%20" sesuai dengan query search twitter
          $i = 0;
          $stringSearch = '';
          $delimiter = ' ';
          // mengambil keyword post dari form
          $str = $_POST['keyword'];
          // memecah keyword berdasarkan karakter spasi dan disimpan di array words
          $words = explode($delimiter, $str);
          // menambahkan karakter "%20" di setiap kata yang sudah dipecah
          foreach ($words as $word) {
            $stringSearch .= $word . "%20";
          }
          // mengambil html berdasarkan url pencarian
          $html = file_get_html("https://twitter.com/search?q=$stringSearch&src=typed_query");

          // echo $html;

/////////////////////////////////////////////////CRAWLING/////////////////////////////////////////////////////////////////////////////
          // looping sebanyak jumlah data yang ada di element div kelas tweet
          foreach ($html->find('div[class="tweet"]') as $tweets) {
            // crawling id user
            $id_user = $tweets->find('span[class="username u-dir u-textTruncate"]', 0)->innertext;
            // menghapus karakter @ pada id user
            $res_user = str_replace('@', '', $id_user);
            // crawling tweet content
            $tweetContent = $tweets->find('p[class="TweetTextSize  js-tweet-text tweet-text"]', 0)->plaintext;
            // menghapus karkter html pada variable tweet content
            $tweetContentFiltered = strip_tags($tweetContent);
            // memfilter gambar dan link
            $tweetContentFiltered = preg_replace("/ <img\s(.+?)>/is", "", $tweetContentFiltered);
            $tweetContentFiltered = preg_replace("/<a\s(.+?)>(.+?)<\/a>/is", "$2", $tweetContentFiltered);
            // melakukan stem dan stop word
            $stemContent = $stemmer->stem($tweetContentFiltered);
            $stopContent = $stopword->remove($stemContent);
            $testStopped[] = $stopContent;
          }
          // menhitung jumlah test data
          $arrTestCount = count($testStopped);

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
          // mengclone data training ke dalam array train_clone
          $train_clone = $sample_data_stopped;
          // looping sebanyak jumlah data testing
          // teknik training dan testingnya adalah semisal ada data training 
          // berjumlah 1000 (misal Tweet 1-1000) dan data crawling 20 (misal Tweet 1001-1020) 
          // maka training dengan 1000 data testing data ke 1001
          // training dengan 1001 data testing data ke 1002
          // training dengan 1002 data testing data ke 1003 dst
          for ($i = 0; $i < $arrTestCount; $i++) {
            $testStoppedFixed = array();
            $sample_data_stopped = $train_clone;
            $arrCloneHelper[] = $testStopped[$i];
            // membuat object tokenizer
            $tf = new TokenCountVectorizer(new WhitespaceTokenizer());
            // mentrain data training
            $tf->fit($sample_data_stopped);
            // menambah kan data testing ke array train_clone
            $train_clone[] = $testStopped[$i];

            // mengambil jumlah data training yang sudah di-fit
            $arrTrainCount = count($sample_data_stopped);
            // mentransform data training
            $tf->transform($sample_data_stopped);
            
            // melakukan pembobotan TF-IDF
            $tfidf = new TfIdfTransformer($sample_data_stopped);
            $tfidf->transform($sample_data_stopped);

            $testStoppedFixed[] = $sample_data_stopped[$arrTrainCount - 1];
            unset($sample_data_stopped[$arrTrainCount - 1]);
            
            // Membuat object distance sesuai radio yang dipilih
            $distanceType = $_POST['rmethod'];
            if ($distanceType == "Overlap") $distance = new Overlap();
            else if ($distanceType == "Jaccard") $distance = new Jaccard();
            else if ($distanceType == "Cosine") $distance = new Cosine();

            // mengisi variable kvalue dengan nilai k yang berjumlah data training dibagi 3
            $kValue = floor(count($sample_data_stopped) / 3);

            //membuat object knearestneighbors
            $classifier = new KNearestNeighbors($kValue, $distance);
            $classifier->train($sample_data_stopped, $dataSentimentKata);
            // looping isi dari data testing
            foreach ($testStoppedFixed as $ters) {
              // memprediksi nilai sentiment dari data testing
              $result = $classifier->predict($ters);
              // Menghitung setiap label yang ada
              if ($result == "Negatif") {
                $countNeg++;
              } else if ($result == "Netral") {
                $countNeut++;
              } else if ($result == "Positif") {
                $countPos++;
              }
              // memasukkan hasil prediksi ke dalam array arr_result
              $arr_result[] = $result;
            }
          //looping isi dari arrclonehelper yaitu data testing
            foreach ($arrCloneHelper as $testtt) {
              $sample_data_stopped[] = $testtt;
            }
          }
          ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
          // menampilkan hasil crawling dan prediksi sentimennya
          echo "<h3><b>Hasil Crawling dan Analisis Sentimen</b></h3>";
          echo "<table border='1'>";
          echo "<tr id='head'>
              <th>User</th>
              <th>Tweets</th>
              <th>Label Sentimen</th>
              </tr>";
          $countss = 1;
          $i = 0;
          foreach ($html->find('div[class="tweet"]') as $tweets) {
            
            $id_user = $tweets->find('span[class="username u-dir u-textTruncate"]', 0)->innertext;
            $res_user = str_replace('@', '', $id_user);
            $tweetContent = $tweets->find('p[class="TweetTextSize  js-tweet-text tweet-text"]', 0)->innertext;
            $tweetContent = strip_tags($tweetContent);
            
            if ($countss % 2 == 0) {
              echo "<tr id = 'gray'>";
            } else {
              echo "<tr id = 'white'>";
            }
            $countss++;
            
            echo "<td>";
            echo $res_user;
            echo "</td>";
            echo "<td>";
            echo $tweetContent;
            echo "</td>";

            echo "<td>";
            echo $arr_result[$i];
          
            echo "</td>";
            echo "</tr>";
            if ($arr_result[$i] == 'Positif') {
              $isPos = '1.0';
            } else if ($arr_result[$i] == 'Netral') {
              $isPos = '0.5';
            } else {
              $isPos = '0.0';
            }
            $tweetContentFiltered = str_replace(array('<b>', '</b>', '<strong>', '</strong>', '<s>', '</s>'), '', $tweetContent);
            $tweetContentFiltered = preg_replace("/ <img\s(.+?)>/is", "", $tweetContentFiltered);
            $tweetContentFiltered = preg_replace("/<a\s(.+?)>(.+?)<\/a>/is", "$2", $tweetContentFiltered);


            $res_user_filtered = str_replace(array('<b>', '</b>'), '', $res_user);
            // melakukan insert ke dalam database
            $sql = "INSERT INTO tweets(content,user_id,isPositive) VALUES(?,?,?)";
            $stmt = $mysqli->prepare($sql);
            $similarity = 0;
            $stmt->bind_param("ssd",$tweetContentFiltered,$res_user_filtered,$isPos);
            $stmt->execute();
            $i++;
          }

          echo "</table>";
          // Membuat Pie Chart hasil prediksi sentimen
          echo "<div id='piechart'></div>";

          echo "<script type='text/javascript' src='https://www.gstatic.com/charts/loader.js'></script>";

          echo "<script type='text/javascript'>";

          echo "google.charts.load('current', {'packages':['corechart']});";
          echo "google.charts.setOnLoadCallback(drawChart);";

          echo "function drawChart() {";
          echo "var data = google.visualization.arrayToDataTable([
      ['Sentiment', 'Number'],
  ['Negatif', " . $countNeg . "],
  ['Netral', " . $countNeut . "],
  ['Positif', " . $countPos . "]
  ]);";

          echo "var options = {'title':'', 'width':550, 'height':400};";

          echo "var chart = new google.visualization.PieChart(document.getElementById('piechart'));";
          echo "chart.draw(data, options);";
          echo "}";
          echo "</script>";
        }
        $mysqli->close();
        ?>

      </div>
    </div>

  </form>

</body>

</html>