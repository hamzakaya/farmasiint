<?php

  // kaynak
  $site_url = 'https://www.farmasiint.com';
  $cdn_link = 'https://cdn.farmasi.com.tr'; //resim linkleri için

  $resim_indir  = false;                      // resimleri otomatik indirsin mi ? true-false
  $resim_dizin  = realpath(".").'/resimler';  // resimleri indireceği dizin

  // test push



  header('Content-Type: application/json');
  if(isset($_GET['getir'])){

    switch ($_GET['getir']) {
      case 'ana-kategoriler':
        $ana_kategoriler = array();
        foreach(kategori_listesi($site_url) as $key => $val){
          $ana_kategoriler[$key] = $val['kategoriAdi'];
        }
        echo json_encode($ana_kategoriler);
      break;

      case 'alt-kategoriler':
        $katID = (int)$_GET['ana-kategoriID'];
        $alt_kategoriler = array();
        $j = 0;
        foreach(kategori_listesi($site_url)[$katID] as $key => $val){ $j++;
          if($j > 1){
            $kat_no = explode('/', $val['link']);

            $alt_kategoriler[$key] = array(
              'id' => $kat_no[3],
              'sira' => $j,
              'kategoriAdi' => $val['kategoriAdi'],
              'link' => $val['link'],
            );
          }
        }
        echo json_encode($alt_kategoriler);

      break;

      case 'en-alt-kategoriler':
        $katID    = (int)$_GET['ana-kategoriID'];
        $altKatID = (int)$_GET['alt-kategoriID'];

        echo json_encode(kategori_listesi($site_url)[$katID]["alt_kategori_{$altKatID}"]['en_alt_kategoriler']);
      break;


      case 'urun-listele':
        $katLink  = $_GET['link'];
        echo json_encode(kategorinin_urunleri($katLink));
      break;


      case 'urun-detay':
        $urunLink  = $_GET['link'];
        echo json_encode(urun_detay_data($urunLink));
      break;


      default:
        echo json_encode(array('error' => true, 'hata' => 'tanımsız parametre'));
      break;
    }
  }else{
    echo json_encode(array('error' => true, 'hata' => 'parametre yok'));
  }




  function kategori_listesi($site_url){
    $data = curlBot($site_url.'/anasayfa');
    $ana_kategoriler = $alt_kategoriler = $en_alt_kategoriler = $kategoriListe = array();
    preg_match_all('@<div class="vcenter">(.*?)</div>@si', $data, $ana_kategorilerData);
    foreach($ana_kategorilerData[1] as $k => $v) $ana_kategoriler[$k+1] = $kategoriListe[$k+1]['kategoriAdi'] = $v;


    foreach($ana_kategoriler as $kategoriID => $anakategori_Adi){
        preg_match_all('@<div class="products-item  clearfix" id="menu-'.$kategoriID.'"><div class="products-list-holder clearfix"><div class="products-column (.*?)">(.*?)</div></div>(.*?)</div>@si', $data, $altData);
        preg_match_all('@<ul>(.*?)</ul>@si', $altData[2][0], $alt);

        foreach($alt[1] as $i => $menuData){
          preg_match_all('@<li><a href="(.*?)">(.*?)</a></li>@si', $menuData, $alt_data);


          $j = 0;
          foreach ($alt_data[2] as $ii => $en_alt_kategori_ismi) {
            $id2 = $i+1;


            if($ii == 0){
              $link_bul = explode('"',$alt_data[1][$ii]);

              $alt_kategoriler[$kategoriID]['kategoriAdi'] = $kategoriListe[$kategoriID]['alt_kategori_'.$id2]['kategoriAdi'] = $en_alt_kategori_ismi;
              $alt_kategoriler[$kategoriID]['link'] = $kategoriListe[$kategoriID]['alt_kategori_'.$id2]['link'] = $link_bul[0];

            }else{
              $j++;
              $kategoriListe[$kategoriID]['alt_kategori_'.$id2]['en_alt_kategoriler'][$j]['kategoriAdi'] = $en_alt_kategori_ismi;
              $kategoriListe[$kategoriID]['alt_kategori_'.$id2]['en_alt_kategoriler'][$j]['link'] = $alt_data[1][$ii];
            }

          }

        }
    }
    return $kategoriListe;
  }

  function kategorinin_urunleri($kategori_link){
    global $site_url;
    $data = curlBot($site_url.$kategori_link);

    preg_match_all('@<p id="Body_Body_Paginator_pCount" class="shop-result-count">Toplam (.*?) sayfa ve (.*?) &#252;r&#252;n</p>@si', $data, $sonuc_sayisi);
    preg_match_all('@<div class="product-info">(.*?)</div>@si', $data, $ilk_sayfa_urunleri);



    $urun_sayisi  = $sonuc_sayisi[2][0]; // toplam ürün sayısı
    $sayfa_sayisi = $sonuc_sayisi[1][0]; // toplam sayfa sayısı

    $urun_listesi = array();
    $urun_say = 0;
    /*
    foreach ($ilk_sayfa_urunleri[1] as $i => $v) {
        $urun_say++;
        $urun_data = urun_data($v);
        $urun_listesi[$urun_say] = $urun_data;
        foreach(urun_detay_data($urun_data['urun_link']) as $kk => $vv) $urun_listesi[$urun_say][$kk] = $vv;
    }
    */

    for($s = 1; $s <= $sayfa_sayisi; $s++){
      $data_page = curlBot($site_url."{$kategori_link}?page={$s}");
      preg_match_all('@<div class="product-info">(.*?)</div>@si', $data_page, $sonraki_sayfalar);

      /*  ürün datasını ayıkla  */
      foreach ($sonraki_sayfalar[1] as $i => $v) {
          $urun_say++;
          $urun_data = urun_data($v);
          $urun_listesi[$urun_say] = $urun_data;
          foreach(urun_detay_data($urun_data['urun_link']) as $kk => $vv) $urun_listesi[$urun_say][$kk] = $vv;
      }
    }
    return $urun_listesi;
  }

  function urun_data($v){
    global $site_url,$cdn_link, $resim_indir, $resim_dizin;
    preg_match('@<p class="product-name"><a href="(.*?)">(.*?)</a></p>@si', $v, $urun_adi);
    preg_match('@<p class="product-price sale" >(.*?)</p>@si', $v, $urun_fiyat_eski);
    preg_match('@<p class="product-price">(.*?)</p>@si', $v, $urun_fiyat_yeni);
    $urun_kodu  = end(explode(' - ', $urun_adi[2]));
    $resim_link = $cdn_link."/product/normal/{$urun_kodu}.jpg";
    $resim_yolu = $resim_dizin."/{$urun_kodu}.jpg";

    if($resim_indir):
      resim_indir($resim_link, $resim_yolu); //resimleri otomatik indiriyor
    endif;


    return array(
      'urun_kodu' => $urun_kodu,
      'urun_adi'  => $urun_adi[2],
      'urun_resim'  => $resim_link,
      'urun_resim_yolu'  => $resim_yolu,
      'urun_link' => $urun_adi[1],
      'urun_link_tam' => $site_url,$urun_adi[1],
      'eski_fiyat' => (int)$urun_fiyat_eski[1],
      'yeni_fiyat' => (int)$urun_fiyat_yeni[1],
    );
  }

  function urun_detay_data($urunLink){
    global $site_url;
    $data = curlBot($site_url.$urunLink);
    preg_match_all('@<div class="product-name">(.*?)</div>@si', $data, $urun_isim);
    preg_match_all('@<div class="tags">(.*?)</div>@si', $data, $etiket_data);
    preg_match_all('@<p>(.*?)</p>@si', $data, $urun_tanitim);
    preg_match_all('@<a href="#">(.*?)</a>@si', $etiket_data[1][0], $etiketler);
    preg_match_all('@<meta property="og:description" content="(.*?)" />@si', $data, $description);
    $yazi = $description[1];

    return array(
      'urun_adi_kodsuz'       => $urun_isim[1][0],
      'tanitim_yazisi'  => replaceSpace($urun_tanitim[1][0]),
      'etiket_liste'    => $etiketler[1],
      'etiket_text'     => implode(', ', $etiketler[1]),
      'description1'     => implode(', ', $yazi),
      'description2'     => htmlspecialchars_decode($yazi),
    );
  }



  function resim_indir($url,$filename){
      if(file_exists($filename)){
          @unlink($filename);
      }
      $fp = fopen($filename,'w');
      if($fp){
          $ch = curl_init ($url);
          curl_setopt($ch, CURLOPT_HEADER, 0);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
          $result = parse_url($url);
          curl_setopt($ch, CURLOPT_REFERER, $result['scheme'].'://'.$result['host']);
          curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
          $raw=curl_exec($ch);
          curl_close ($ch);
          if($raw){
              fwrite($fp, $raw);
          }
          fclose($fp);
          if(!$raw){
              @unlink($filename);
              return false;
          }
          return true;
      }
      return false;
  }

  function replaceSpace($string){
		$string = preg_replace("/\s+/", " ", $string);
		$string = trim($string);
		return $string;
	}

  function curlBot($site_url , $timeout = 5){
    $ch = curl_init();
    $tarayici = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:32.0) Gecko/20100101 Firefox/32.0';

    curl_setopt($ch, CURLOPT_URL,$site_url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1);
    curl_setopt($ch, CURLOPT_HEADER         , 0);
    curl_setopt($ch, CURLOPT_TIMEOUT        , $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT      , $tarayici);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
  }
