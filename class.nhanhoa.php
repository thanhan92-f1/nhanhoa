<?php

class Nhanhoa extends DomainModule implements Observer, DomainSuggestionsInterface, DomainPriceImport, DomainModuleListing, DomainLookupInterface, DomainPremiumInterface, DomainBulkLookupInterface
{

    protected $version = "1.1.0";
    protected $description = "Nhan Hoa domain registrar module by Pho Tue SoftWare Solutions JSC";
    protected $lang = array("english" => array("Nhanhoausername" => "User Name", "Nhanhoapassword" => "Password", "Nhanhoaid" => "User ID"));
    protected $commands = array (
      "Register" , //Đăng ký tên miền
      "Transfer", // Transfer tên miền
      "Renew", // Gia hạn tên miền
      "ContactInfo", // Thông tin Tên miền
      "RegisterNameServers", // Đăng ký NameServer
      "DNSmanagement",// Quản lý DNS
      "getNameServers",// Lấy dữ liệu NameServer
      "EppCode",// Get Mã EPPcode Transfer Domain
      'Unlock24h',// 
      'ResendVerify',
      'SkipVerifyEmail',
      'ManualRegister',
      'LockDomain',
      'Contract',
      'getStatusDomain',
      'checkBackorderStatus',
      'getDomainInfo',
      'checkDomainDeclarationStatus',
      'updateRegistrarLock',
      'updateNameServers',
      "updateContactInfo",
      "Statusdomain",
      "backorderDomainVN",
      "getekyc",
      "getekycProfile",
    );
    protected $clientCommands = array(
       "ContactInfo",
       "DNSmanagement",
       "Unlock24h",
       "EmailForwarding",
       "RegisterNameServers",
       "EppCode",
       "Contract",
      
  );
    protected $configuration = array(
      "username" => array("value" => "", "type" => "input", "default" => false),
      "password" => array("value" => "", "type" => "password", "default" => false),
      "id" => array("value" => "", "type" => "input", "default" => false),
      "Template to download" => array ("value" => "",  "type" => "input",  "default" => array(),  "description" => "")
    );
    // public function install()
    // {
    //     $this->upgrade("1.0");
    // }
    public function upgrade($old)
    {

    }



    public function LockDomain(){


      $formdata = [];
      $formdata['domainNameList'] = $this->options['sld'] . '.' . $this->options['tld'];
      $formdata['domainName'] = $this->options['sld'];
      $formdata['domainExt'] = $this->options['tld'];
      $formdata['domainYear'] = $this->options['numyears'];
      $formdata['domainDNS1'] = 'ns1.photuesoftware.vn';
      $formdata['domainIP1'] = gethostbyname($this->options['ns1']);
      $formdata['domainDNS2'] = 'ns1.photuesoftware.vn';
      $formdata['domainIP2'] = gethostbyname($this->options['ns2']);

      $getNS = $this->getNameServers();
      $change = array(
        array("from" => $getNS[0], "to" => "ns1.photuesoftware.vn", "name" => "Name Server " . $i),
        array("from" => $getNS[1], "to" => "ns2.photuesoftware.vn", "name" => "Name Server " . $i)
      );

       $i = 1;
       for ($j = 1; isset($this->options["ns" . $i]); $i++) {
           $ns = $this->options["ns" . $i];
           if ($ns != $getNS[$i - 1]) {
               $change[] = array("from" => $getNS[$i - 1], "to" => $ns == "" ? "empty" : $ns, "name" => "Name Server " . $i);
           }
           if ($this->options["ns" . $i] != "") {
               $nshash["ns" . $j] = $this->options["ns" . $i];
               $j++;
           }
       }

      if (empty($change)) {
        $this->addError('đã xảy ra lỗi, vui lòng thử lại');
          return false;
      }
      $cmd = substr(".".$this->options["tld"], -3, 3) == '.vn' ? 'change_dnsdomainvn' : 'change_dnsdomain';
      $result = $this->get($cmd, array(), $formdata);
      if (!empty($result) && strtolower($result['status']) == 'ok') {
          $this->logAction(array("action" => "Lock Domain", "result" => true, "change" => $change, "error" => false));
          $this->addInfo($result["msg"]);
            $this->addInfo('Đã khoá domain theo yêu cầu');
          return true;
      } else {
          $this->logAction(array("action" => "Lock Domain", "result" => false, "change" => $change, "error" => true));
          $this->addError($result["msg"]);
          return false;
      }
    }


    /**
     *
     */
    public function __construct()
     {
         parent::__construct();
         #licensesnippet#
         $this->domain_contacts = [
          'registrant' => ['firstname' => 'ok']
         ];
     }

     protected  $domain_contacts = [
       'registrant' => ['firstname' => '1']
     ];

    public function testConnection()
    {
        // $this->upgrade("1.0");
        $cmd = 'get_balance';
        $postfields["auth-user"] = $this->configuration["username"]["value"];
        $postfields["auth-pwd"] = $this->configuration["password"]["value"];
        $postfields["auth-id"] = $this->configuration["id"]["value"];
        $formdata = "";

        $result = $this->get($cmd, $postfields, $formdata);
        if (!$result) {
            return false;
        }
        if ($result["status"] != 'ok') {
            $this->addError($result['msg']);
            return false;
        }
        $this->addInfo('Số dư tài khoản ' . number_format($result['balance']));
        return true;
    }

    protected function get($cmd, $postfields = array(), $formdata)
    {

        HBDebug::debug("NhanHoa request", $formdata);
        $root_domain = "http://api.nhanhoa.com/";
        $postdata = "";
        $postfields["auth-user"] = $this->configuration["username"]["value"];
        $postfields["auth-pwd"] = $this->configuration["password"]["value"];
        $postfields["auth-id"] = $this->configuration["id"]["value"];
        if ($cmd == "") {
            return array("status"=> "error", "msg" => "Không có lệnh để thực thi API.");
        }
        $url = $root_domain."?act=".$cmd;

        foreach ($formdata as $fname => $fkey) {
            $postdata .= "{$fname}=".urlencode(str_replace('&amp;', '&', $fkey))."&";
        }
        $postfields["cmd"] = $cmd;
        $postfields["formdata"] = $postdata;

        $user_agent = "Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data, true);
    }
    public function checkAvailability()
    {
        $cmd = 'whois';
        $formdata = array(
        "domain" => $this->options["sld"],
        "ext" => $this->options["tld"],
        "type" => "0",
      );
        $result = $this->get($cmd, array(), $formdata);
        if (!$result) {
            return false;
        }
        if ($result["status"] == "ok") {
            $return = array("available" => true);
            if ($result["premium"] == 1) {
                $return["premium"] = array(
              "register" => $result['premium_price'],
              "currency" => "VND"
            );
            }
            return $return;
        }

        $this->addError("Wrong response from the server while checking availability");
        return false;
    }

    protected function makeContact($type = "registrant", $cdata = false)
    {
        $formdata = [];
        if ($this->options['registrant']['companyname']) {
            $formdata['owner_type'] = 1;
            $formdata['congty_ten'] = $this->options['registrant']["companyname"];
            $formdata['congty_mst'] = $this->options['registrant']["taxid"];
            $formdata['congty_diachi'] = $this->options['registrant']["address1"] . ', ' . $this->options['registrant']["city"] . ', ' . $this->options['registrant']["state"] . ', ' . $this->options['registrant']["country"];
            $voice = $this->options['registrant']["phonenumber"];
            $phone = Utilities::get_phone_info($this->options['registrant']["phonenumber"], $this->options['registrant']["country"]);
            if ($phone["number"]) {
                $voice = "+" . $phone["ccode"] . "." . $phone["number"];
            }
            $formdata['congty_dt'] = $voice;
        }
        if ($type == "registrant") {
            $formdata['ord_owner_type'] =  $this->options['registrant']["companyname"] ? 1 : 0;
            $formdata['ownerName'] = $this->options['registrant']["companyname"] ? $this->options['registrant']["companyname"] : $this->options['registrant']["lastname"] . ' ' . $this->options['registrant']["firstname"];
            $formdata['ownerAddress'] = $this->options['registrant']["address1"];
            $formdata['ownerTown'] = $this->options['registrant']["ward"];
            $formdata['ownerDistrict'] = $this->options['registrant']["city"];
            $formdata['ownerCity'] = $this->options['registrant']["state"];
            $formdata['ownerCountry'] = $this->options['registrant']["country"] == 'VN' ? 'Vietnam' : $this->options['registrant']["country"];
            $formdata['ownerEmail'] = $this->options['registrant']["email"];
            $formdata['ownerMail'] = $this->options['registrant']["email"];
            $formdata['ownerGender'] = $this->options['registrant']["gender"];
            $formdata['ownerBirthday'] = $this->options['registrant']["birthday"] ? $this->options['registrant']["birthday"] : '01/01/1990';
            $formdata['ownerPersonID'] = $this->options['registrant']["nationalid"];
            $voice = $this->options['registrant']["phonenumber"];
            $phone = Utilities::get_phone_info($this->options['registrant']["phonenumber"], $this->options['registrant']["country"]);
            if ($phone["number"]) {
                $voice = "+" . $phone["ccode"] . "." . $phone["number"];
            }
            $formdata['ownerPhone'] = $voice;
        }
        if ($type == "admin") {
            $formdata['adminName'] = $this->options['admin']["lastname"] . ' ' . $this->options['admin']["firstname"];
            $formdata['adminAddress'] = $this->options['admin']["address1"];
            $formdata['adminTown'] = $this->options['admin']["ward"];
            $formdata['adminDistrict'] = $this->options['admin']["city"];
            $formdata['adminCity'] = $this->options['admin']["state"];
            $formdata['adminCountry'] = $this->options['admin']["country"] == 'VN' ? 'Vietnam' : $this->options['admin']["country"];
            $formdata['adminEmail'] = $this->options['admin']["email"];
            $formdata['adminGender'] = $this->options['admin']["gender"];
            $formdata['adminBirthday'] = $this->options['admin']["birthday"] ? $this->options['admin']["birthday"] : '01/01/1990';
            $formdata['adminPersonID'] = $this->options['admin']["nationalid"];
            $voice = $this->options['admin']["phonenumber"];
            $phone = Utilities::get_phone_info($this->options['admin']["phonenumber"], $this->options['admin']["country"]);
            if ($phone["number"]) {
                $voice = "+" . $phone["ccode"] . "." . $phone["number"];
            }
            $formdata['adminPhone'] = $voice;
        }
        if ($type == "tech") {
            $formdata['techName'] = $this->options['tech']["lastname"] . ' ' . $this->options['tech']["firstname"];
            $formdata['techAddress'] = $this->options['tech']["address1"];
            $formdata['techTown'] = $this->options['tech']["ward"];
            $formdata['techDistrict'] = $this->options['tech']["city"];
            $formdata['techCity'] = $this->options['tech']["state"];
            $formdata['techCountry'] = $this->options['tech']["country"] == 'VN' ? 'Vietnam' : $this->options['tech']["country"];
            $formdata['techEmail'] = $this->options['tech']["email"];
            $formdata['techGender'] = $this->options['tech']["gender"];
            $formdata['techBirthday'] =  $this->options['tech']["birthday"] ? $this->options['tech']["birthday"] : '01/01/1990';
            $formdata['techPersonID'] = $this->options['tech']["nationalid"];
            $voice = $this->options['tech']["phonenumber"];
            $phone = Utilities::get_phone_info($this->options['tech']["phonenumber"], $this->options['tech']["country"]);
            if ($phone["number"]) {
                $voice = "+" . $phone["ccode"] . "." . $phone["number"];
            }
            $formdata['techPhone'] = $voice;
        }
        if ($type == "billing") {
            $formdata['billingName'] = $this->options['billing']["lastname"] . ' ' . $this->options['billing']["firstname"];
            $formdata['billingAddress'] = $this->options['billing']["address1"];
            $formdata['billingTown'] = $this->options['billing']["ward"];
            $formdata['billingDistrict'] = $this->options['billing']["city"];
            $formdata['billingCity'] = $this->options['billing']["state"];
            $formdata['billingCountry'] = $this->options['billing']["country"] == 'VN' ? 'Vietnam' : $this->options['billing']["country"];
            $formdata['billingEmail'] = $this->options['billing']["email"];
            $formdata['billingGender'] = $this->options['billing']["gender"];
            $formdata['billingBirthday'] =  $this->options['billing']["birthday"] ? $this->options['billing']["birthday"] : '01/01/1990';
            $formdata['billingPersonID'] = $this->options['billing']["nationalid"];
            $voice = $this->options['billing']["phonenumber"];
            $phone = Utilities::get_phone_info($this->options['billing']["phonenumber"], $this->options['billing']["country"]);
            if ($phone["number"]) {
                $voice = "+" . $phone["ccode"] . "." . $phone["number"];
            }
            $formdata['billingPhone'] = $voice;
        }
        // Contact cho domain quốc tế
        if ($type == "all") {
            $formdata['domain_realname'] = $this->options['registrant']['lastname'] . ' ' . $this->options['registrant']['firstname'];
            $formdata['domain_address'] = $this->options['registrant']['address1'] . ', ' . $this->options['registrant']['city'];
            $formdata['domain_city'] = $this->options['registrant']['state'];
            $formdata['domain_country'] = $this->options['registrant']['country'] == 'VN' ? 'Viet Nam' :  $this->options['registrant']['country'];
            $formdata['domain_company'] = $this->options['registrant']['companyname'];
            $formdata['domain_email'] = $this->options['registrant']['email'];
            $formdata['domain_username'] = $this->options['registrant']['email'];
            $voice = $this->options['registrant']["phonenumber"];
            $phone = Utilities::get_phone_info($this->options['registrant']["phonenumber"], $this->options['registrant']["country"]);
            if ($phone["number"]) {
                $voice = "+" . $phone["ccode"] . "." . $phone["number"];
            }
            $formdata['domain_phone'] = $voice;
        }
        return $formdata;
    }

    public function ManualRegister(){
        $this->options['manual_reg'] = true;
        $this->Register();
    }

    public function Register()
    {  




        $availability = $this->lookupDomain($this->options["sld"], "." . $this->options["tld"]);
        if (!$availability["available"]) {
            $this->addError('Tên miền không thể đăng ký');
            return false;
        }

$item_check = [
    'gov.vn', 'name.vn', 'vn', 'id.vn', 'edu.vn',
    'thanhphohochiminh.vn', 'angiang.vn', 'bacgiang.vn', 'backan.vn', 'baclieu.vn', 'bacninh.vn',
    'baria-vungtau.vn', 'bentre.vn', 'binhdinh.vn', 'binhduong.vn', 'binhphuoc.vn', 'binhthuan.vn',
    'camau.vn', 'cantho.vn', 'caobang.vn', 'daklak.vn', 'daknong.vn', 'danang.vn', 'dienbien.vn',
    'dongnai.vn', 'dongthap.vn', 'gialai.vn', 'hagiang.vn', 'haiduong.vn', 'haiphong.vn', 'hanam.vn',
    'hanoi.vn', 'hatay.vn', 'hatinh.vn', 'haugiang.vn', 'hoabinh.vn', 'hungyen.vn', 'khanhhoa.vn',
    'kiengiang.vn', 'kontum.vn', 'laichau.vn', 'lamdong.vn', 'langson.vn', 'laocai.vn', 'longan.vn',
    'namdinh.vn', 'nghean.vn', 'ninhbinh.vn', 'ninhthuan.vn', 'phutho.vn', 'phuyen.vn', 'quangbinh.vn',
    'quangnam.vn', 'quangngai.vn', 'quangninh.vn', 'quangtri.vn', 'soctrang.vn', 'sonla.vn', 'tayninh.vn',
    'thaibinh.vn', 'thainguyen.vn', 'thanhhoa.vn', 'thuathienhue.vn', 'tiengiang.vn', 'travinh.vn',
    'tuyenquang.vn', 'vinhlong.vn', 'vinhphuc.vn', 'yenbai.vn', 'org.vn'
];
        $item_for_company = ['org.vn'];
        $item_for_person = ['name.vn'];


        if (in_array($this->options['tld'], $item_check) && !$this->options['manual_reg']) {
            $this->addError('Cần kiểm tra thông tin chủ thể trước khi đăng ký.');
            return false;
        }

        if (in_array($this->options['tld'], $item_for_company) && !$this->domain_contacts['registrant']['companyname']) {
            $this->addError('Đối tượng đăng ký phải là công ty/tổ chức.');
            return false;
        }

        if (in_array($this->options['tld'], $item_for_person) && $this->domain_contacts['registrant']['companyname']) {
            $this->addError('Đối tượng đăng ký phải là cá nhân.');
            return false;
        }

        $formdata = [];
        $formdata['domainNameList'] = $this->options['sld'] . '.' . $this->options['tld'] ;
        $formdata['domainName'] = $this->options['sld'];
        $formdata['domainExt'] = $this->options['tld'];
        $formdata['domainYear'] = $this->options['numyears'];
        $formdata['idprotection'] = $this->options['idprotection'];
        $formdata['domainDNS1'] = $this->options['ns1'];
        $formdata['domainIP1'] = gethostbyname($this->options['ns1']);
        $formdata['domainDNS2'] = $this->options['ns2'];
        $formdata['domainIP2'] = gethostbyname($this->options['ns2']);
        if ($this->options['ns3']) {
            $formdata['domainDNS3'] = $this->options['ns3'];
            $formdata['domainIP3'] = gethostbyname($this->options['ns3']);
        }
        if ($this->options['ns4']) {
            $formdata['domainDNS4'] = $this->options['ns4'];
            $formdata['domainIP4'] = gethostbyname($this->options['ns4']);
        }
        if ($this->options['ns5']) {
            $formdata['domainDNS5'] = $this->options['ns5'];
            $formdata['domainIP5'] = gethostbyname($this->options['ns5']);
        }
        if (substr(".".$this->options["tld"], -3, 3) == '.vn') {
            $cmd = 'register_domainvn';
            $formdata = array_merge($formdata, $this->makeContact('registrant'));
            $formdata = array_merge($formdata, $this->makeContact('admin'));
            $formdata = array_merge($formdata, $this->makeContact('tech'));
            $formdata = array_merge($formdata, $this->makeContact('billing'));
        } else {
            $cmd = 'register_domain';
            $formdata = array_merge($formdata, $this->makeContact('all'));
        }

        $ex = [
          'registrant' => $this->domain_contacts['registrant'],
          'admin' => $this->domain_contacts['admin'],
          'tech' => $this->domain_contacts['tech'],
          'billing' => $this->domain_contacts['billing']
        ];
         $this->updateExtended($ex);

        $check = $this->check_input($formdata);
        if ($check['status'] == 'success') {
            $result = $this->get($cmd, array(), $formdata);
            if (!empty($result) && strtolower($result['status']) == 'ok') {
                $this->addDomain("Active");
                $this->addInfo($result["msg"]);
                return true;
            } else {
                $this->addError($result["msg"]);
                return false;
            }
        } else {
            $this->addError($check['msg']);
            return false;
        }
        return false;
    }
    public function Transfer()
    {
        $formdata = [];
        $formdata['domainNameList'] = $this->options['sld'] . '.' . $this->options['tld'] ;
        $formdata['domainName'] = $this->options['sld'];
        $formdata['domainExt'] = $this->options['tld'];
        $formdata['domainYear'] = $this->options['numyears'];
        $formdata['idprotection'] = $this->options['idprotection'];
        $formdata['auth-code'] = $this->options["epp_code"];
        $formdata['domainDNS1'] = $this->options['ns1'];
        $formdata['domainIP1'] = gethostbyname($this->options['ns1']);
        $formdata['domainDNS2'] = $this->options['ns2'];
        $formdata['domainIP2'] = gethostbyname($this->options['ns2']);
        if ($this->options['ns3']) {
            $formdata['domainDNS3'] = $this->options['ns3'];
            $formdata['domainIP3'] = gethostbyname($this->options['ns3']);
        }
        if ($this->options['ns4']) {
            $formdata['domainDNS4'] = $this->options['ns4'];
            $formdata['domainIP4'] = gethostbyname($this->options['ns4']);
        }
        if ($this->options['ns5']) {
            $formdata['domainDNS5'] = $this->options['ns5'];
            $formdata['domainIP5'] = gethostbyname($this->options['ns5']);
        }
        if (substr(".".$this->options["tld"], -3, 3) == '.vn') {
            $cmd = 'transfer_domainvn';
            $formdata = array_merge($formdata, $this->makeContact('registrant'));
            $formdata = array_merge($formdata, $this->makeContact('admin'));
            $formdata = array_merge($formdata, $this->makeContact('tech'));
            $formdata = array_merge($formdata, $this->makeContact('billing'));
        } else {
            $cmd = 'transfer_domain';
            $formdata = array_merge($formdata, $this->makeContact('all'));
        }
        $result = $this->get($cmd, array(), $formdata);
        if (!empty($result) && strtolower($result['status']) == 'ok') {
            $this->addDomain("Pending Transfer");
            $this->addInfo($result["msg"]);
            return true;
        } else {
            $this->addError($result["msg"]);
            return false;
        }
        return false;
    }
    public function Renew()
    {
        if (substr(".".$this->options["tld"], -3, 3) == '.vn') {
            $cmd = 'renew_domainvn';
        } else {
            $cmd = 'renew_domain';
        }
        $formdata = [];
        $formdata['domainNameList'] = $this->options['sld'] . '.' . $this->options['tld'];
        $formdata['domainName'] = $this->options['sld'];
        $formdata['domainExt'] = $this->options['tld'];
        $formdata['domainYear'] = $this->period;
        $result = $this->get($cmd, array(), $formdata);
        HBDebug::debug('NhanHoaREnew', $result );
        if (!empty($result) && strtolower($result['status']) == 'ok') {

            if($this->status == HBC\DOMAIN_STATE_EXPIRED){
              $this->addDomain("Expired");
            }

            $this->addPeriod();
            $this->addInfo($result["msg"]);
            $this->addDomain("Active");
            return true;
        } else {
            $this->addError($result["msg"]);
            return false;
        }
    }

     public function expiredDomains()
     {
       return false;
     }
     public function getAutoRenew()
     {
      return false;
     }
     public function updateAutoRenew()
     {
       return false;
     }
    public function getNameServers()
    {
        $cmd = substr(".".$this->options["tld"], -3, 3) == '.vn' ? 'get_infodomainvn' : 'get_infodomain';
        $formdata = array('is_whmcs' => '1', 'domainName' => $this->options['sld'] . '.' . $this->options['tld']);
        $result = $this->get($cmd, array(), $formdata);

        $data=[];
        if (strtoupper($result["status"]) == "OK") {

            if (substr($this->options['tld'], -2, 2) == 'vn') {
                $data[] = $result['content']['subDomainName1'];
                $data[] = $result['content']['subDomainName2'];
                if ($result['content']['subDomainName3']) {
                    $data[] = $result['content']['subDomainName3'];
                }
                if ($result['content']['subDomainName4']) {
                    $data[] = $result['content']['subDomainName4'];
                }

            } else {
                $data[] = $result['content']['dns_inter'];
                $data[] = $result['content']['dns_inter2'];
                if ($result['content']['dns_inter3']) {
                    $data[] = $result['content']['dns_inter3'];
                }
                if ($result['content']['dns_inter4']) {
                    $data[] = $result['content']['dns_inter4'];
                }
            }
            return $data;
        } else {
            $this->addError($result['msg']);
            return false;
        }
    }
    public function updateNameServers()
    {
        $formdata = [];
        $formdata['domainNameList'] = $this->options['sld'] . '.' . $this->options['tld'];
        $formdata['domainName'] = $this->options['sld'];
        $formdata['domainExt'] = $this->options['tld'];
        $formdata['domainYear'] = $this->options['numyears'];
        $formdata['domainDNS1'] = $this->options['ns1'];
        $formdata['domainIP1'] = gethostbyname($this->options['ns1']);
        $formdata['domainDNS2'] = $this->options['ns2'];
        $formdata['domainIP2'] = gethostbyname($this->options['ns2']);
        if ($this->options['ns3']) {
            $formdata['domainDNS3'] = $this->options['ns3'];
            $formdata['domainIP3'] = gethostbyname($this->options['ns3']);
        }
        if ($this->options['ns4']) {
            $formdata['domainDNS4'] = $this->options['ns4'];
            $formdata['domainIP4'] = gethostbyname($this->options['ns4']);
        }
        if ($this->options['ns5']) {
            $formdata['domainDNS5'] = $this->options['ns5'];
            $formdata['domainIP5'] = gethostbyname($this->options['ns5']);
        }
        $change = array();
        $getNS = $this->getNameServers();
        $i = 1;
        for ($j = 1; isset($this->options["ns" . $i]); $i++) {
            $ns = $this->options["ns" . $i];
            if ($ns != $getNS[$i - 1]) {
                $change[] = array("from" => $getNS[$i - 1], "to" => $ns == "" ? "empty" : $ns, "name" => "Name Server " . $i);
            }
            if ($this->options["ns" . $i] != "") {
                $nshash["ns" . $j] = $this->options["ns" . $i];
                $j++;
            }
        }
        if (empty($change)) {
            return false;
        }
        $cmd = substr(".".$this->options["tld"], -3, 3) == '.vn' ? 'change_dnsdomainvn' : 'change_dnsdomain';
        $result = $this->get($cmd, array(), $formdata);
        if (!empty($result) && strtolower($result['status']) == 'ok') {
            $this->logAction(array("action" => "Change Nameserver", "result" => true, "change" => $change, "error" => false));
            $this->addInfo($result["msg"]);
            return true;
        } else {
            $this->logAction(array("action" => "Change Nameserver", "result" => false, "change" => $change, "error" => true));
            $this->addError($result["msg"]);
            return false;
        }
        return false;
    }
    
   public function getContactInfo()
    {
        $formdata = array(
          "domainName" => $this->options['sld'] . '.' . $this->options['tld']
      );
        if (substr(".".$this->options["tld"], -3, 3) == '.vn') {
            $cmd = 'get_infodomainvn';
            $result = $this->get($cmd, array(), $formdata);
            if (!empty($result) && strtolower($result['status']) == 'ok') {
                $result = $result['content'];
                $cityName = $this->formatCityName($result['ownerProvinceList']);
                $adminCity = $this->formatCityName($result['adminProvinceList']);
                $techCity = $this->formatCityName($result['techProvinceList']);
                $billingCity = $this->formatCityName($result['billingProvinceList']);

                if ($result['ord_owner_type'] == 1) {
                    $contact['registrant'] = array(
                      "companyname" =>  $result['newDomain.ownerName'],
                      "Tên Giao Dịch" => $result['newDomain.transactionName'],
                      "taxid" => $result['newDomain.ownerTax'],
                      "mail" => $result['newDomain.ownerMail'],
                      "firstname" => $result['newDomain.adminName'],
                      "Chức danh" => $result['newDomain.admin2Position'],
                      "birthday" => $result['newDomain.adminbirthDate'],
                      "nationalid" =>  $result['newDomain.admin2IDPP'],
                      "phonenumber" => $result['newDomain.ownerPhone'],
                      "address1" => $result['newDomain.ownerAddress'],
                      "ward" => $result['ownerTownList'],
                      "city" => $result['ownerDistrictList'],
                      "state" => $cityName,
                      "country" => $result['ownerCountry'] ? $result['ownerCountry'] : 'VN',
                      "NS1" => $result['subDomainName1'],
                      "IP NS1" => $result['subDomainIp1'],
                      "NS2" => $result['subDomainName2'],
                      "IP NS2" => $result['subDomainIp2'],
                      "NS3" => $result['subDomainName3'],
                      "IP NS3" => $result['subDomainName3'],
                      
                      
                    );
                } else {
                    $contact['registrant'] = array(
                    "firstname" => $result['newDomain.ownerName'],
                    "birthday" => $result['newDomain.ownerbirthDate'],
                    "nationalid" =>  $result['newDomain.adminIDPP'],
                    "gender" => $result['newDomain.adminGender'],
                    "email" => $this->options['registrant']["email"],
                    "phonenumber" => $result['newDomain.ownerPhone'],
                    "address1" => $result['newDomain.ownerAddress'],
                    "ward" => $result['ownerTownList'],
                    "city" => $result['ownerDistrictList'],
                    "state" => $cityName,
                    "country" => $result['ownerCountry'] ? $result['ownerCountry'] : 'VN',
                    "NS1" => $result['subDomainName1'],
                    "IP NS1" => $result['subDomainIp1'],
                    "NS2" => $result['subDomainName2'],
                    "IP NS2" => $result['subDomainIp2'],
                    "NS3" => $result['subDomainName3'],
                    
                );
                }

                $contact['admin'] = array(
                    "firstname" => $result['newDomain.adminName'],
                    "birthday" => $result['newDomain.adminbirthDate'],
                    "nationalid" =>  $result['newDomain.adminIDPP'],
                    "gender" => $result['newDomain.adminGender'],
                    "email" => $result['newDomain.adminEmail'],
                    "phonenumber" => $result['newDomain.ownerPhone'],
                    "address1" => $result['newDomain.ownerAddress'],
                    "ward" => $result['ownerTownList'],
                    "city" => $result['ownerDistrictList'],
                    "state" => $cityName,
                    "country" => $result['ownerCountry'] ? $result['ownerCountry'] : 'VN'
                );
                $contact['tech'] = array(
                    "firstname" => $result['newDomain.techName'],
                    "birthday" => $result['newDomain.techbirthDate'],
                    "nationalid" =>$result['newDomain.techIDPP'],
                    "gender" =>$result['newDomain.adminGender'],
                    "email" => $result['newDomain.adminEmail'],
                    "phonenumber" => $result['newDomain.ownerPhone'],
                    "address1" => $result['techAddress'],
                    "ward" => $result['techTownList'],
                    "city" => $result['techDistrictList'],
                    "state" => $techCity,
                    "country" => $result['ownerCountry'] ? $result['ownerCountry'] : 'VN'
                );
                $contact['billing'] = array(
                    "firstname" => $result['cus_payment_realname'],
                    "birthday" => $result['newDomain.adminbirthDate'],
                    "email" => $result['cus_payment_email'],
                    "phonenumber" => $result['cus_payment_phone'],
                    "address1" => $result['cus_payment_address'],
                    "ward" => $result['billingTownList'],
                    "city" => $result['billingDistrictList'],
                    "state" => $billingCity,
                    "country" => $result['adminCountry'] ? $result['adminCountry'] : 'VN'
                );
                return $contact;
            }
        } else {
            $cmd = 'get_infodomain';
            $result = $this->get($cmd, array(), $formdata);
            if (!empty($result) && strtolower($result['status']) == 'ok') {
                $resp = $result['content'];
                $resp['firstname'] = end(explode(" ", $resp['domain_realname']));
                $resp['lastname'] = implode(" ", \array_diff(explode(" ", $resp['domain_realname']), [$resp['firstname']]));
                $resp['city'] = end(explode(", ", $resp['domain_address']));
                $resp['address1'] = implode(", ", \array_diff(explode(", ", $resp['domain_address']), [$resp['city']]));
                $contact['registrant'] = array(
                  "firstname" => $resp["firstname"],
                  "lastname" => $resp["lastname"],
                  "companyname" => $resp["domain_company"],
                  "email" => $resp["domain_email"],
                  "address1" => $resp["address1"],
                  "Quận/ Huyện" => $resp["city"],
                  "state" => $resp["domain_city"],
                  "country" => $resp["domain_country"] ? $resp["domain_country"] : 'VN',
                  "phonenumber" => $resp["domain_phone"]
                );
                $contact['admin'] = $contact['tech'] = $contact['billing'] = array(
                  "firstname" => $resp["firstname"],
                  "lastname" => $resp["lastname"],
                  "email" => $resp["domain_email"],
                  "address1" => $resp["address1"],
                  "Quận/Huyện" => $resp["city"],
                  "state" => $resp["domain_city"],
                  "country" => $resp["domain_country"] ? $resp["domain_country"] : 'VN',
                  "phonenumber" => $resp["domain_phone"]
                );
                return $contact;
            }
        }

        return false;
    }
private function formatCityName($cityName)
{
    // Chuẩn hóa các ký tự và khoảng trắng
    $cityName = trim($cityName);

    // Xử lý trường hợp đặc biệt "TP HCM"
    if (strtoupper($cityName) === 'TP HCM') {
        return "Thành phố Hồ Chí Minh";
    }

    // Danh sách các thành phố lớn
    $majorCities = ['Hồ Chí Minh', 'Hải Phòng', 'Cần Thơ', 'Hà Nội', 'Đà Nẵng', 'Huế'];

    // Kiểm tra và thêm tiền tố phù hợp
    if (in_array($cityName, $majorCities)) {
        return "Thành phố " . $cityName;
    } else {
        return "Tỉnh " . $cityName;
    }
}



    public function updateContactInfo()
    {
        $formdata = [];
        $formdata['is_whmcs'] = 1;
        $formdata['domainNameList'] = $this->options['sld'] . '.' . $this->options['tld'];
        $formdata['domainName'] = $this->options['sld'];
        $formdata['domainExt'] = $this->options['tld'];
        $formdata['domainYear'] = $this->options['numyears'];
        $formdata['domainDNS1'] = $this->options['ns1'];
        $formdata['domainIP1'] = gethostbyname($this->options['ns1']);
        $formdata['domainDNS2'] = $this->options['ns2'];
        $formdata['domainIP2'] = gethostbyname($this->options['ns2']);
        if ($this->options['ns3']) {
            $formdata['domainDNS3'] = $this->options['ns3'];
            $formdata['domainIP3'] = gethostbyname($this->options['ns3']);
        }
        if ($this->options['ns4']) {
            $formdata['domainDNS4'] = $this->options['ns4'];
            $formdata['domainIP4'] = gethostbyname($this->options['ns4']);
        }
        if ($this->options['ns5']) {
            $formdata['domainDNS5'] = $this->options['ns5'];
            $formdata['domainIP5'] = gethostbyname($this->options['ns5']);
        }
        if (substr(".".$this->options["tld"], -3, 3) == '.vn') {
            $cmd = 'change_infodomainvn';
            $formdata = array_merge($formdata, $this->makeContact('registrant'));
            $formdata = array_merge($formdata, $this->makeContact('admin'));
            $formdata = array_merge($formdata, $this->makeContact('tech'));
            $formdata = array_merge($formdata, $this->makeContact('billing'));

        } else {
            $cmd = 'change_infodomain';
            $formdata = array_merge($formdata, $this->makeContact('all'));

        }
        $cmd = substr(".".$this->options["tld"], -3, 3) == '.vn' ? 'change_infodomainvn' : 'change_infodomain';
        $result = $this->get($cmd, array(), $formdata);
        if (!empty($result) && strtolower($result['status']) == 'ok') {
            $this->addInfo($result['msg']);
            $this->logAction(array("action" => "Update Contact Info", "result" => true, "change" => false, "error" => false));
            return true;
        } else {
            $this->logAction(array("action" => "Update Contact Info", "result" => false, "change" => false, "error" => "Unable to create new contact"));
            $this->addError($result['msg']);
            return false;
        }
    }
    public function getRegistrarLock()
    {
      $cmd = 'domain_tf_status';
    	$formdata = array(
    		'is_whmcs' => '1',
    		'domainName' => $this->options['sld'] . '.' . $this->options['tld']
    	);
    	$result = $this->get($cmd, [], $formdata);
    	if ($result['status'] == 'error') {
    		$this->addError($result["msg"]);
        return false;
    	}
      if ($result['lockstatus'] == 'locked') {
         $this->addInfo('Domain is ' . $result['lockstatus']);
        return true;
      } else {
         $this->errorInfo('Domain is ' . $result['lockstatus']);
        return false;
      }

    }
    public function updateRegistrarLock()
    {
      $cmd = 'domain_unlock';
      if ($this->options["registrarLock"] == 1) {
          $cmd = 'domain_lock';
      }
      $formdata = array(
    		'is_whmcs' => '1',
    		'domainName' => $this->options['sld'] . '.' . $this->options['tld']
    	);
      $result = $this->get($cmd, [], $formdata);
    	if ($result['status'] == 'error') {
    		$this->addError($result["msg"]);
        $this->logAction(array("action" => "Domain Registrar Lock", "result" => false, "change" => $cmd, "error" => $result["msg"]));
        return false;
    	}
      $this->logAction(array("action" => "Domain Registrar Lock", "result" => true, "change" => $cmd, "error" => false));
      return true;
    }
    
    public function getEppCode()
    {
        if (substr(".".$this->options['tld'], -3, 3) == '.vn') {
            $this->addError('Không khả dụng với tên miền .vn');
            return false;
        }
        $cmd = 'domain_authcode';
        $formdata = array(
            'is_whmcs' => '1',
            'domainName' => $this->options['sld'] . '.' . $this->options['tld']
        );
        $result = $this->get($cmd, array(), $formdata);
        if (!empty($result) && strtolower($result['status']) == 'ok') {
            $epp = (string) $result['authcode'];
            $this->logAction(array("action" => "Get Epp Code", "result" => $result['msg'], "change" => $epp, "error" => false));
            return "The EPP key is: " . $epp;
        } else {
            $this->logAction(array("action" => "Get Epp Code", "result" => false, "change" => false, "error" => $result['msg']));
            $this->addError($result['msg']);
            return $result['msg'];
        }
    }
    public function synchInfo()
    {
        $formdata = array('is_whmcs' => '1', 'domainName' => $this->options['sld'] . '.' . $this->options['tld']);
        $cmd = 'domain_sync';
        $result = $this->get($cmd, array(), $formdata);
HBDebug::debug('NhanhoaSynch', $result);
        if (strtoupper($result["status"]) == "OK") {
            $expirytime = $currentstatus = "";
            $expirytime = $result["endtime"];
            $currentstatus = $result["currentstatus"];
            $return = array();
            $return["expires"] = date("Y-m-d", $expirytime);

            switch ($currentstatus) {
              case 'Active':
                $return["status"] = "Active";
                break;
              case 'Expired':
                $return["status"] = "Expired";
                break;
              case 'Suspended':
              $return["status"] = "Expired";
                break;
              case 'Expired and Cancelled':
                $return["status"] = "TransferredOut";
                break;
              case 'InActive':
                if ($this->domain_type == 'Transfer') {
                  $return["status"] = "Pending Transfer";
                } else {
                  $return["status"] = "Pending Registration";
                }
                break;
              default:
                break;
            }
            $return["ns"] = $this->getNameServers();
            if (substr(".".$this->options['tld'], -3, 3) != '.vn') {
                $return["reglock"] = $this->getRegistrarLock();
            }
            HBEventManager::notify("after_nhanhoasynch", array("domain_id" => $this->domain_id, "name" => $this->name, "synch" => $return));
            return $return;
        } else {

            if ($this->status === 'Active') {
                $return["status"] = "TransferredOut";
            }
            $this->addError($result['msg']);
            HBEventManager::notify("after_nhanhoasynch", array("domain_id" => $this->domain_id, "name" => $this->name, "synch" => $return));
            // return $return;
        }
    }
    public function updateIDProtection()
    {
        $cmd = "whois_protect";
        $formdata = array(
          "domainName" => $this->options['sld'] . '.' . $this->options['tld'],
          "protect" => $this->options["idprotection"],
        );
        if ($this->details['idprotection'] == $this->options["idprotection"]) {
           $this->addError('idprotection is same configuration');
           return false;
        }
        $result = $this->get($cmd, array(), $formdata);
        if ($this->options["idprotection"] == 1) {
            $change = array("from" => "Disabled", "to" => "Enabled");
        } else {
            $change = array("from" => "Enabled", "to" => "Disabled");
        }
        if (!empty($result) && strtolower($result['status']) == 'ok') {
            $this->addDomain("Active");
            $this->addPeriod();
            $this->addInfo($result['msg']);
            $this->logAction(array("action" => "Update ID Protection", "result" => true, "change" => $change, "error" => false));
            return true;
        } else {
            $this->addError($result['msg']);
            $this->logAction(array("action" => "Update ID Protection", "result" => false, "change" => false, "error" => $result['msg']));
            return false;
        }
    }
    protected function isExpired()
    {
        return false;
    }
    /**
     * List domains managed by registry.
     * Returned data is in form of an array with keys like in hb_domains,
     *
     * @return array[] [
     *  'name' => 'name',
     *  '... other keys are optional'
     * ]
     */
    public function ListDomains() {
    $cmd = "list_domain";
    $formdata = ["type" => 1];
    $domains = [];
    $result1 = $this->get($cmd, [], $formdata);
    
    $formdata = ["type" => 0];
    $result2 = $this->get($cmd, [], $formdata);
    
    
    // $result = array_merge($result1, $result2);
    
    // if (!empty($result1) && strtolower($result1['status']) === 'ok')
    
    // var_dump($result1); // Debug dữ liệu trả về
    // var_dump("Hello world");


    if (!is_array($result1)) {
        $this->addError('Dữ liệu trả về không hợp lệ');
        return [];
    }
    
    if (!is_array($result2)) {
        $this->addError('Dữ liệu trả về không hợp lệ');
        return [];
    }

    if (!empty($result1) && strtolower($result1['status']) === 'ok') {
        foreach ($result1['data'] as $domain) {
            $domain_name = $domain['domain'];
            $status = 'Active';
            $period = '1';
            $date = new DateTime(); // Get the current date and time
            $date->modify('-3 day');
            $date_created = $date->format('Y-m-d');
            
            $date_expires = $domain['expired_date'];
            $date = DateTime::createFromFormat('d-m-Y', $date_expires);
            $date_expires = $date->format('Y-m-d');
            
            $domains[] = [
                'name' => $domain_name,
                'period' => $period,
                'status' => $status,
                'date_created' => $date_created,
                'expires' => $date_expires
            ];
        }
    }
    
    if (!empty($result2) && strtolower($result2['status']) === 'ok') {
        foreach ($result2['data'] as $domain) {
            $domain_name = $domain['domain'];
            $status = 'Active';
            $period = '1';
            $date = new DateTime(); // Get the current date and time
            $date->modify('-3 day');
            $date_created = $date->format('Y-m-d');
            $date_expires = $domain['expired_date'];
            $date = DateTime::createFromFormat('d-m-Y', $date_expires);
            $date_expires = $date->format('Y-m-d');
            
            $domains[] = [
                'name' => $domain_name,
                'period' => $period,
                'status' => $status,
                'date_created' => $date_created,
                'expires' => $date_expires
            ];
        }
    }
    // var_dump($domains);
    return $domains;
    }
    /**
     * Return prices for TLDs supported by this registrar
     */
   public function getDomainPrices()
{
    $cmd = 'domain_pricing';
    $domains = [];
    $specialExtensions = ['.dghc']; // Đuôi đặc biệt
    $provinceDomains = [
        'angiang.vn', 'bacgiang.vn', 'backan.vn', 'baclieu.vn', 'bacninh.vn',
        'baria-vungtau.vn', 'bentre.vn', 'binhdinh.vn', 'binhduong.vn', 'binhphuoc.vn',
        'binhthuan.vn', 'camau.vn', 'cantho.vn', 'caobang.vn', 'daklak.vn', 'daknong.vn',
        'danang.vn', 'dienbien.vn', 'dongnai.vn', 'dongthap.vn', 'gialai.vn', 'hagiang.vn',
        'haiduong.vn', 'haiphong.vn', 'hanam.vn', 'hanoi.vn', 'hatay.vn', 'hatinh.vn',
        'haugiang.vn', 'hoabinh.vn', 'hungyen.vn', 'khanhhoa.vn', 'kiengiang.vn', 'kontum.vn',
        'laichau.vn', 'lamdong.vn', 'langson.vn', 'laocai.vn', 'longan.vn', 'namdinh.vn',
        'nghean.vn', 'ninhbinh.vn', 'ninhthuan.vn', 'phutho.vn', 'phuyen.vn', 'quangbinh.vn',
        'quangnam.vn', 'quangngai.vn', 'quangninh.vn', 'quangtri.vn', 'soctrang.vn', 'sonla.vn',
        'tayninh.vn', 'thaibinh.vn', 'thainguyen.vn', 'thanhhoa.vn', 'thuathienhue.vn',
        'tiengiang.vn', 'travinh.vn', 'tuyenquang.vn', 'vinhlong.vn', 'vinhphuc.vn', 'yenbai.vn',
        'org.vn'
    ];

    $types = [1, 2]; // Lấy dữ liệu cho cả loại 1 và 2
    foreach ($types as $type) {
        $result = $this->get($cmd, [], ['type' => $type]);

        if (!empty($result) && strtolower($result['status']) == 'ok') {
            foreach ($result['data'] as $domain) {
                $name = $domain['name'];

                // Xử lý trường hợp .dghc
                if (in_array($name, $specialExtensions)) {
                    foreach ($provinceDomains as $provinceDomain) {
                        $fullDomain = ".$provinceDomain"; // Thêm dấu chấm trước tên miền
                        $period = [];
                        for ($i = 1; $i < 10; $i++) {
                            $period[$i] = [
                                'period' => $i,
                                'register' => $domain['price_register'] + ($i - 1) * $domain['price_renew'],
                                'renew' => $i * $domain['price_renew'],
                                'transfer' => $domain['price_transfer'] + ($i - 1) * $domain['price_renew'],
                                'redemption' => 15 * $domain['price_renew'],
                            ];
                        }
                        $domains[$fullDomain] = $period;
                    }
                    continue; // Bỏ qua các xử lý khác cho .dghc
                }

                // Bỏ qua tên miền có dấu gạch dưới
                if (strpos($name, '_') !== false) {
                    break;
                }

                // Xử lý các tên miền .vn khác
                if (substr($name, -3) == '.vn') {
                    $domain['price_transfer'] = 0;
                }

                $period = [];
                for ($i = 1; $i < 10; $i++) {
                    if ($i === 1) {
                        $period[$i] = [
                            'period' => $i,
                            'register' => $domain['price_register'],
                            'transfer' => $domain['price_transfer'],
                            'renew' => $domain['price_renew'],
                            'redemption' => 15 * $domain['price_renew'],
                        ];
                    } else {
                        if ($domain['price_register']) {
                            $period[$i]['register'] = $domain['price_register'] + ($i - 1) * $domain['price_renew'];
                        }
                        if ($domain['price_renew']) {
                            $period[$i]['renew'] = $i * $domain['price_renew'];
                        }
                        if ($domain['price_transfer']) {
                            $period[$i]['transfer'] = $domain['price_transfer'] + ($i - 1) * $domain['price_renew'];
                        }
                    }
                }
                $domains[$name] = $period;
            }
        }
    }

    $pricing = [
        'VND' => $domains,
    ];

    return $pricing;
}
    /**
     * @param string $sld Domain name without TLD
     * @param string $tld Domain TLD, starting with dot.
     *
     * @param array $settings array with keys:
     *  - adult: true/false
     *  - maxresults: true/false
     *  - tlds: array - list of tlds to limit results to, if no limits it will be empty
     *  - language: string - user interface language
     *  - clientip: string - ip address of user searching for domain
     *  -
     * @return array List of domain names that are available for registration
     * AND POSSIBLY NON PREMIUM
     */
    public function suggestDomains($sld, $tld, $settings = array())
    {

    }
    //  public function getRAA($name)
    //  {
    //   return false;
    //  }
    public function lookupBulkDomains($sld, $tlds = array())
    {
        $return = array("available" => false);
        $cmd = 'whois_multi';

        $formdata = array(
        "domain" => $sld,
        "ext" => implode(',', $tlds)
      );

        $result = $this->get($cmd, array(), $formdata);
        if (!$result) {
            return false;
        }
        if ($result['status'] == 'ok') {
            foreach ($result['data'] as $tld) {
                $return[$tld]['available'] = false;
                if ($tld['status'] == '0') {
                    if ($tld["premium"] == "true") {
                        $return[$tld['ext']]["premium"] = array(
                "register" => $tld['premium_price'],
                "currency" => "VND"
              );
                    }
                    $return[$tld['ext']]['available'] = true;
                }
            }
        }
        return $return;
    }

    public function lookupDomain($sld, $tld, $settings = array())
    {
        $return = array("available" => false);
        $cmd = 'whois';
        $postfields = [];
        $formdata = array(
          "domain" => $sld,
          "ext" => $tld,
          "type" => "0",
        );
        $result = $this->get($cmd, $postfields, $formdata);

        if (!$result) {
            return false;
        }

        if ($result['status'] === 'ok') {
          if ($result['domain_status'] == 0) {
              if ($result["premium"] == 1) {
                  $return["premium"] = array(
                    "register" => $result['premium_price'],
                    "currency" => "VND"
                  );
              }
              $return["available"] = true;
          }
        }

        return $return;
    }
        
public function Contract()
{
    // Xác định lệnh API cho từng loại tên miền
    $cmd_vn = "get_sign";             // API cho tên miền .vn
    $cmd_international = "get_sign_qt"; // API cho tên miền quốc tế

    // Lấy thông tin tên miền
    $domain_name = $this->options['sld'] . '.' . $this->options['tld'];
    $tld = $this->options['tld'];

    // Kiểm tra tên miền có phải .vn hay không
    if ($tld === 'vn' || preg_match('/\.vn$/', $domain_name)) {
        $cmd = $cmd_vn; // Chọn lệnh cho tên miền .vn
    } else {
        $cmd = $cmd_international; // Chọn lệnh cho tên miền quốc tế
    }

    // Chuẩn bị dữ liệu gửi đi
    $formdata = array(
        "domain_name" => $domain_name,
    );

    try {
        // Gọi API và xử lý phản hồi
        $result = $this->get($cmd, array(), $formdata);

        // Xử lý lỗi khi không có phản hồi
        if (!$result) {
            $this->addError('Không thể kết nối tới API.');
            return false;
        }

        // Kiểm tra phản hồi từ API
        if ($result['status'] == 'ok') {
            $signUrl = $result['sign_url'];

            // Hiển thị thông báo link ký số thành công
            $this->addInfo("Lấy link ký số thành công: <a href='{$signUrl}' target='_blank'>{$signUrl}</a>");
            return $signUrl;
        } else {
            // Thêm thông báo lỗi từ API
            $this->addError("API báo lỗi: {$result['msg']}");
            return false;
        }
    } catch (Exception $e) {
        // Xử lý ngoại lệ
        $this->addError("Lỗi ngoại lệ: " . $e->getMessage());
        return false;
    }
}

    public function getTemplate()
    {
        $q = $this->db->query("SELECT * FROM hb_templates WHERE `name` IN ('BanKhaiVN', 'BanKhaiVN-custom') ORDER BY `updated` DESC LIMIT 1");
        $d = $q->fetch(PDO::FETCH_ASSOC);
        $q->closeCursor();
        return $d;
    }
    protected function getDomainFile($domain_id)
    {
        if (!$domain_id) {
            return false;
        }
        $q = $this->db->prepare("SELECT * FROM hb_downloads WHERE `rel_type` & :type AND `rel_id` = :id ORDER BY `id` DESC LIMIT 1");
        $q->execute(array(":type" => Download::TYPE_DOMAIN, ":id" => $domain_id));
        $file = $q->fetch(PDO::FETCH_ASSOC);
        $q->closeCursor();
        if (!$file) {
            return false;
        }
        $path = Engine::singleton()->getConfig("HBDownloadsDir") . DS . $file["filename"];
        return $path;
    }

    public function setProduct($id){

    }

    protected function check_input($data = array())
    {
        $msg = [];
        foreach ($data as $key => $value) {
            $key = strtolower($key);
            $value = trim($value);

            if (preg_match('/[*]/', $value)) {
                $msg[] = 'Dữ liệu không được có dấu (*) ' . $value;
            }
            if ((preg_match('/birthday/', $key) == true) && ($data['ord_owner_type'] == 0)) {
                if ($this->isValidBirthday($value) == false) {
                    $msg[]= 'Ngày sinh không đúng định dạng (dd/mm/yyyy) hoặc tuổi nhỏ hơn 15!' . $value;
                }
            }
            if ($key == 'ownerName' && $data['ord_owner_type'] == 0) {
                $value = trim($value);
                if (empty($value)) {
                    $msg[] = 'Vui lòng không bỏ trống tên chủ thể';
                }
            }
            if (preg_match('/address/i', $key)) {
                $value = trim($value);
                if (empty($value)) {
                    $msg[] = 'Vui lòng không bỏ trống địa chỉ địa chỉ chủ thể';
                }
            }
            if ($key == 'ownerName' && $data['ord_owner_type'] == 1) {
                $value = trim($value);
                if (empty($value)) {
                    $msg[] = 'Quý khách đăng ký với hồ sơ công ty / tổ chức. Vui lòng nhập tên công ty / tổ chức';
                }
            }
            if (preg_match('/ownerPersonID/i', $key) && $data['ord_owner_type'] == 0) {
                if (empty($value)) {
                    $msg[] = 'Vui lòng không bỏ trống địa chỉ CMND/Passport';
                }
                 if (!preg_match('/^(00|01|02|03|04|05|06|07|08|09|10|11|12|13|14|15|16|17|18|19|20|21|22|23|24|25|26|27|280|281|285|29|30|31|32|33|34|35|36|37|38|40)[0-9]{6,}/', $value) && !preg_match('/^[A-Z]{1,2}[0-9]+[A-Z0-9]*/i', $value)) {
                      $msg = array('status' => 'error', 'msg' => 'Thông tin số CMND/Passport không hợp lệ. Vui lòng kiểm tra lại');
                     return $msg;
                 }
            }

            // $check_name = $CMS->class->seo->remove_vietnamese(trim($domain));
            if ($key == 'ownerName' && $data['ord_owner_type'] == 1) { //ACC hồ sơ tổ chức
              $value = $this->convertAccentsAndSpecialToNormal(trim($value));
              if(preg_match("/(tap doan|cong ty|to chuc|hoi dong|cơ quan|lien doan|cong dong|uy ban|truong ban|ban quan|benh vien|trung tam)/", strtolower($value)) == false) {
                $msg[] = 'Hồ sơ công ty nhưng tên chủ thể không phải là công ty';
              }
            }


        }
        if ($msg) {
            return array('status' => 'error', 'msg' => $msg);
        }

        return array('status' => 'success', 'msg' => '');
    }
    protected function isValidBirthday($cnt_birthday)
    {
        list($dd, $mm, $yyyy) = explode('/', $cnt_birthday);
        if (!checkdate($mm, $dd, $yyyy)) {
            return false;
        }
        $current_year = date('Y');
        $remain_year = intval($current_year - $yyyy);
        if ($remain_year < 15) {
            return false;
        }
        return true;
    }

    public function SkipVerifyEmail(){
      if (substr(".".$this->options['tld'], -3, 3) == '.vn') {
          $this->addError('Không khả dụng với tên miền .vn');
          return false;
      }
      $cmd = 'skip_verify_domain';
      $postfields = [];
      $formdata = array(
        "domainName" => $this->name
      );
      $result = $this->get($cmd, $postfields, $formdata);
      if (!$result) {
          $this->addError('Lỗi ko xác định');
      }
      if ($result['status'] == 'ok') {
        $this->addInfo($result['msg']);
        $this->logAction(array("action" => "Skip verify email", "result" => $result, "change" => $result['msg'], "error" => false));
      } else {
        $this->addError($result['msg']);
        $this->logAction(array("action" => "Skip verify email", "result" => $result, "change" => false, "error" => $result['msg']));
      }
      return true;
    }
    public function ResendVerify(){
      if (substr(".".$this->options['tld'], -3, 3) == '.vn') {
          $this->addError('Không khả dụng với tên miền .vn');
          return false;
      }
      $cmd = 'resend_verify_domain';
      $postfields = [];
      $formdata = array(
        "domainName" => $this->name
      );
      $result = $this->get($cmd, $postfields, $formdata);
      if (!$result) {
          $this->addError('Lỗi ko xác định');
      }
      if ($result['status'] == 'ok') {
        $this->addInfo($result['msg']);
      } else {
        $this->addError($result['msg']);
      }
      $this->logAction(array("action" => "Resend verify email", "result" => $result, "change" => false, "error" => $result['msg']));
      return true;
    }


    public function Unlock24h(){

      $cmd = 'unlock24h';
      $postfields = [];
      $formdata = array(
        "domainName" => $this->name
      );
      $result = $this->get($cmd, $postfields, $formdata);
      if (!$result) {
          $this->addError('Lỗi ko xác định');
      }
      if ($result['status'] == 'ok') {
        $this->addInfo($result['msg']);
      } else {
        $this->addError($result['msg']);
      }
      $this->logAction(array("action" => "Unlock 24h", "result" => $result, "change" => false, "error" => $result['msg']));
      return true;
    }
 
public function getDomainInfo()
{
    $cmd = 'info_control';
    $postfields = [
        "auth-user" => $this->configuration["username"]["value"],
        "auth-pwd" => $this->configuration["password"]["value"],
        "auth-id" => $this->configuration["id"]["value"]
    ];
    $formdata = array(
        "domain_name" => $this->name
    );

    $result = $this->get($cmd, $postfields, $formdata);
    if (!$result) {
        $this->addError('Lỗi ko xác định');
        return false;
    }
    if ($result['status'] == 'ok') {
        $info = 'Lấy thông tin quản lý tên miền thành công.' . PHP_EOL;
        $info .= 'Login URL: ' . $result['login_url'] . PHP_EOL;
        $info .= 'Domain: ' . $result['domain'] . PHP_EOL;
        $info .= 'Password: ' . $result['password'] . PHP_EOL;
        $info .= 'Bạn có thể sao chép thông tin này.'; // Thêm đoạn thông báo cho phép copy

        $this->addInfo($info);
        return array(
            "login_url" => $result['login_url'],
            "domain" => $result['domain'],
            "password" => $result['password']
        );
    } else {
        $this->addError($result['msg']);
        return false;
    }
}


public function Statusdomain()
{
    $cmd = 'check_declaration';
    $formdata = [
        "domainName" => $this->options['sld'] . '.' . $this->options['tld']
    ];

    try {
        // Gọi API với $postfields đã được khai báo
        $result = $this->get($cmd, $postfields, $formdata);

        if (!$result) {
            $this->addError('Lỗi không xác định khi gọi API.');
            return false;
        }

        // Kiểm tra trạng thái từ API
        if (strtolower($result['status']) === 'ok') {
            $statusLabels = [
                0 => 'Đang chờ duyệt',
                1 => 'Hợp lệ',
                2 => 'Không hợp lệ',
                3 => 'Chưa có hồ sơ'
            ];

            // Lấy trạng thái từ kết quả API
            $statusValue = $result['declaration_status'] ?? null;
            $statusText = $statusLabels[$statusValue] ?? 'Chức Năng chỉ hỗ trợ cho Tên Miền Cá Nhân';

            // Ghi log thông tin chi tiết
            $message = "Module (Nhanhoa): Kiểm tra trạng thái hồ sơ" . PHP_EOL;
            $message .= "Tên miền: " . ($result['domain_name'] ?? $formdata['domainName']) . PHP_EOL;
            $message .= "Trạng thái: " . $statusText . PHP_EOL;

            $this->addInfo($message);

            // Trả về kết quả
            return [
                "domain_name" => $result['domain_name'] ?? $formdata['domainName'],
                "declaration_status" => $statusValue,
                "declaration_status_text" => $statusText
            ];
        } else {
            $this->addError("API trả về lỗi: {$result['msg']}");
            return false;
        }
    } catch (Exception $e) {
        $this->addError("Lỗi ngoại lệ: " . $e->getMessage());
        return false;
    }
}

public function backorderDomainVN()
{
    $cmd = 'backorder_domainvn';

    // Chuẩn bị dữ liệu `formdata`
    $formdata = [
        'domainNameList' => $this->options['sld'] . '.' . $this->options['tld'],
        'domainName' => $this->options['sld'],
        'domainExt' => $this->options['tld'],
        'domainYear' => $this->options['numyears'],
        'idprotection' => $this->options['idprotection'],
        'domainDNS1' => $this->options['ns1'],
        'domainIP1' => gethostbyname($this->options['ns1']),
        'domainDNS2' => $this->options['ns2'],
        'domainIP2' => gethostbyname($this->options['ns2']),
    ];

    // Kiểm tra và thêm DNS bổ sung (DNS3, DNS4, DNS5)
    foreach (['ns3', 'ns4', 'ns5'] as $key) {
        if (!empty($this->options[$key])) {
            $index = substr($key, -1); // Lấy số cuối cùng từ key (3, 4, 5)
            $formdata["domainDNS{$index}"] = $this->options[$key];
            $formdata["domainIP{$index}"] = gethostbyname($this->options[$key]);
        }
    }

    // Xử lý đăng ký cho tên miền `.vn` hoặc quốc tế
    if (substr("." . $this->options["tld"], -3, 3) === '.vn') {
        $cmd = 'register_domainvn';
        $formdata = array_merge($formdata, $this->makeContact('registrant'));
        $formdata = array_merge($formdata, $this->makeContact('admin'));
        $formdata = array_merge($formdata, $this->makeContact('tech'));
        $formdata = array_merge($formdata, $this->makeContact('billing'));
    } else {
        $cmd = 'register_domain';
        $formdata = array_merge($formdata, $this->makeContact('all'));
    }

    // Gọi API kiểm tra dữ liệu đầu vào
    $check = $this->check_input($formdata);
    if ($check['status'] === 'success') {
        // Gửi yêu cầu API
        $result = $this->get($cmd, $postfields, $formdata);

        if (!empty($result) && strtolower($result['status']) === 'ok') {
            // Thành công, thêm domain và log thông báo
            $this->addDomain("Active");
            $this->addInfo($result["msg"]);
            return true;
        } else {
            // Thất bại, thêm lỗi từ API
            $this->addError($result["msg"]);
            return false;
        }
    } else {
        // Dữ liệu đầu vào không hợp lệ
        $this->addError($check['msg']);
        return false;
    }
}
public function getekyc()
{
    // Định nghĩa lệnh API cho từng loại tên miền
    $cmd_vn = "get_link_ekyc_tmvn";             // API cho tên miền .vn
    // $cmd_international = "";         // API cho tên miền quốc tế

    // Lấy thông tin tên miền
    $domain_name = $this->options['sld'] . '.' . $this->options['tld'];
    $tld = strtolower($this->options['tld']);   // Chuyển TLD sang chữ thường để so sánh

    // Xác định lệnh API dựa trên loại tên miền
    $cmd = ($tld === 'vn' || preg_match('/\.vn$/', $domain_name)) ? $cmd_vn : $cmd_international;

    // Chuẩn bị dữ liệu gửi đi
    $formdata = [
        "domain_name" => $domain_name,
    ];

    try {
        // Gọi API và xử lý phản hồi
        $result = $this->get($cmd, [], $formdata);

        // Kiểm tra nếu không có phản hồi
        if (!$result) {
            $this->addError('Không thể kết nối tới API.');
            return false;
        }

        // Kiểm tra trạng thái phản hồi từ API
        if (isset($result['status']) && $result['status'] === 'ok') {
            $signUrl = $result['ekyc_url'] ?? '';

            // Hiển thị thông báo link ký số thành công
            $this->addInfo("Lấy link ký số thành công: <a href='{$signUrl}' target='_blank'>{$signUrl}</a>");
            return $signUrl;
        }

        // Thêm thông báo lỗi từ API nếu có
        $errorMsg = $result['msg'] ?? 'Lỗi không xác định từ API.';
        $this->addError("API báo lỗi: {$errorMsg}");
        return false;
    } catch (Exception $e) {
        // Xử lý ngoại lệ và thêm thông báo lỗi
        $this->addError("Lỗi ngoại lệ: " . $e->getMessage());
        return false;
    }
}



public function getStatusDomain()
{
    // Kiểm tra tên miền là .vn hay không
    $domainName = $this->options['sld'] . '.' . $this->options['tld'];
    $cmd = ($this->options['tld'] === 'vn' || preg_match('/\.vn$/', $domainName)) ? 'get_statusdomainvn' : 'get_statusdomain';

    $postfields = [];
    $formdata = array(
        "domainName" => $domainName,
    );

    try {
        // Gọi API và xử lý phản hồi
        $result = $this->get($cmd, $postfields, $formdata);

        // Kiểm tra không có phản hồi
        if (!$result) {
            $this->addError('Lỗi không xác định khi gọi API.');
            return false;
        }

        // Kiểm tra dữ liệu phản hồi hợp lệ
        if (!isset($result['status'])) {
            $this->addError('Phản hồi không hợp lệ từ API.');
            return false;
        }

        // Kiểm tra nếu trạng thái là ok
        if ($result['status'] == 'ok') {
            $this->addInfo('Kiểm tra trạng thái xử lý tên miền thành công.');

            // Kiểm tra dữ liệu cần thiết trong phản hồi
            if (!isset($result['content'], $result['ord_id'], $result['ord_name'], $result['ord_end_time'])) {
                $this->addError('Thiếu dữ liệu trong phản hồi từ API.');
                return false;
            }

            // Định dạng thời gian kết thúc đơn hàng
            $ordEndTimeFormatted = date('d-m-Y H:i:s', $result['ord_end_time']);

            // Trả về dữ liệu
            return array(
                "content" => $result['content'],
                "ord_id" => $result['ord_id'],
                "ord_name" => $result['ord_name'],
                "ord_end_time" => $ordEndTimeFormatted
            );
        } else {
            // Thêm lỗi nếu phản hồi không ok
            $this->addError("API báo lỗi: {$result['msg']}");
            return false;
        }
    } catch (Exception $e) {
        // Xử lý ngoại lệ
        $this->addError("Lỗi ngoại lệ: " . $e->getMessage());
        return false;
    }
}



    
    /**
     * @param $str
     * @return array|string|string[]|null
     */
    private function convertAccentsAndSpecialToNormal($str)
    {
        $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/", 'a', $str);
        $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/", 'e', $str);
        $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/", 'i', $str);
        $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/", 'o', $str);
        $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/", 'u', $str);
        $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/", 'y', $str);
        $str = preg_replace("/(đ)/", 'd', $str);
        $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/", 'A', $str);
        $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/", 'E', $str);
        $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/", 'I', $str);
        $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/", 'O', $str);
        $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/", 'U', $str);
        $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/", 'Y', $str);
        $str = preg_replace("/(Đ)/", 'D', $str);
        return $str;
    }


   public function checkDomainRegistrant(){
    return $this->domain_contacts['registrant'];
   }
   
   public function execute($cmd, $postfields, $formdata) {
    $url = 'http://api.nhanhoa.com/?act=' . $cmd;

    $postfields['cmd'] = $cmd;
    $postfields['formdata'] = http_build_query($formdata);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if (!$response) {
        $this->addError('Không nhận được phản hồi từ API');
        return null;
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->addError('Phản hồi từ API không phải là JSON hợp lệ: ' . json_last_error_msg());
        return null;
    }

    return $result;
}
}
function execute($cmd, $postfields, $formdata) {
    $url = 'http://api.nhanhoa.com/?act=' . $cmd;

    $postfields['cmd'] = $cmd;
    $postfields['formdata'] = http_build_query($formdata);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if (!$response) {
        $this->addError('Không nhận được phản hồi từ API');
        return null;
    }

    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->addError('Phản hồi từ API không phải là JSON hợp lệ: ' . json_last_error_msg());
        return null;
    }

    return $result;
}
