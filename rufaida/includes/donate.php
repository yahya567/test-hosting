<?php
    $access_key = '6ed39d5e068d33bc9c4860dedb57440d';
    $profile_id = '9FB353E2-5B2F-423A-86FC-F0F8EADC4AEC';
    $transaction_uuid = uniqid();
    $reference_number = rand(1000000,9999999);
    $confirm = null;

    if (isset($_POST['confirm'])) {
        $confirm = $_POST['confirm'];
        $donor_name = $_POST['donor_name'];
        $currency = $_POST['currency'];
        $amount = $_POST['amount'];
    }

    define ('HMAC_SHA256', 'sha256');
    define ('SECRET_KEY', '552819b810194bf881a2dc0e5727ab69a332b0c83ae84349a73fe6afc0954d86bd2368a5fe9749b19d427cd799cf8ff073b04ba4f80e412ca689c3e27abeae1d40aa3af5370c4f058d657991bfde0eca5af1d7aaea3d469f9ba33b0f831e64dd4b2038fbb822434c964d8de6870311c1363183f4be9b412fb4344f00472fe5c9');

    function sign ($params) {
        return signData(buildDataToSign($params), SECRET_KEY);
    }

    function signData($data, $secretKey) {
        return base64_encode(hash_hmac('sha256', $data, $secretKey, true));
    }

    function buildDataToSign($params) {
            $signedFieldNames = explode(",",$params["signed_field_names"]);
            foreach ($signedFieldNames as $field) {
            $dataToSign[] = $field . "=" . $params[$field];
            }
            return commaSeparate($dataToSign);
    }

    function commaSeparate ($dataToSign) {
        return implode(",",$dataToSign);
    }

    $params = array(
        "access_key" => $access_key,
        "profile_id" => $profile_id,
        "transaction_uuid" => $transaction_uuid,
        "signed_field_names" => "access_key,profile_id,transaction_uuid,signed_field_names,unsigned_field_names,signed_date_time,locale,transaction_type,reference_number,amount,currency",
        "unsigned_field_names" => "",
        "signed_date_time" => gmdate("Y-m-d\TH:i:s\Z"),
        "locale" => "en",
        "transaction_type" => "sale",
        "reference_number" => $reference_number,
        "amount" => "",
        "currency" => ""
    );


    // remove html special chars
    function display($data) {
        error_log("Data: " . $data);
        return htmlspecialchars(stripslashes(trim($data)));
    }
?>

<!-- Contact Start -->
<div class="container-fluid contact bg-light py-5">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 800px;">
                    <h4 class="text-primary">Donate</h4>
                    <h1 class="display-4 mb-4">Support us in anyway you can</h1>
                </div>
                <div class="row g-5">
                    <div class="col-xl-6 wow fadeInLeft" data-wow-delay="0.2s">
                        <div class="contact-img d-flex justify-content-center" >
                            <div class="contact-img-inner">
                                <img src="img/carousel-2.png" class="img-fluid w-100"  alt="Image">
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 wow fadeInRight" data-wow-delay="0.4s">
                        <div>
                            <h4 class="text-primary">For residents of Ethiopia</h4>
                            <p class="mb-4">If you are a resident of Ethiopia, you can support us directly by depositing any amount of money to our <strong>CBE</strong> bank account <strong>1000504086309</strong> (Rufaida Women's Foundation)</p>
                            <h4 class="text-primary">For international payment</h4>
                            <?php
                                    if ($confirm) {
                                        ?>
                                        <form id="payment_confirmation" action="https://secureacceptance.cybersource.com/pay" method="post"/>
                                            <div style="display: none;">

                                            <?php
                                                foreach($_REQUEST as $name => $value) {
                                                    echo $params[$name] = $value;
                                                }
                                            ?>
                                            </div>

                                            <fieldset id="confirmation">
                                                <legend>Please confirm your donation</legend>
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <div class="form-floating">
                                                            <input type="text" readonly class="form-control border-0" id="name_conform" placeholder="Your name" value="<?php echo ucfirst(display($donor_name)); ?>">
                                                            <label for="name_conform">Name</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12 col-xl-6">
                                                        <div class="form-floating">
                                                            <input type="text" readonly class="form-control border-0" id="currency_confirm" placeholder="Currency" value="<?php echo display($currency); ?>">
                                                            <label for="currency_confirm">Currency</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12 col-xl-6">
                                                        <div class="form-floating">
                                                            <input type="text" readonly name="amount_confirm" class="form-control border-0" id="amount_confirm" placeholder="Amount to donate" value="<?php echo display(number_format($amount, 2)); ?>">
                                                            <label for="amount_confirm">Amount</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <br>
                                                <div>
                                                    <?php
                                                    foreach($params as $name => $value) {
                                                        echo "<div>";
                                                        // echo "<span class=\"fieldName\">" . $name . "</span><span class=\"fieldValue\">" . $value . "</span>";
                                                        echo "</div>\n";
                                                    }
                                                        
                                                    ?>
                                                </div>
                                            </fieldset>
                                            <?php
                                                foreach($params as $name => $value) {
                                                    echo "<input type=\"hidden\" id=\"" . $name . "\" name=\"" . $name . "\" value=\"" . $value . "\"/>\n";
                                                    }
                                                    
                                                    echo "<input type=\"hidden\" id=\"signature\" name=\"signature\" value=\"" . sign($params) . "\"/>\n";
                                                ?>
                                                <!-- js back button -->
                                                <div class="row g-3">
                                                    <div class="col-lg-12 col-xl-6">
                                                        <div class="form-floating">
                                                            <input type="button" class="btn btn-secondary w-100 py-3" value="Back" onclick="history.back()">
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-12 col-xl-6">
                                                        <div class="form-floating">
                                                            <input type="submit" id="submit" class="btn btn-primary w-100 py-3" value="Confirm"/>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>

                                        <?php
                                    } else {
                                        ?>

                                    <form action="" method="POST">
                                        <input type="hidden" name="confirm" value="1">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="form-floating">
                                                    <input type="text" required class="form-control border-0" name="donor_name" id="name" placeholder="Your name">
                                                    <label for="name">Name</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-12 col-xl-6">
                                                <div class="form-floating">
                                                    <select required class="form-select border-0" name="currency" id="currency">
                                                        <option selected value="USD">Currency (Defualt to USD)</option>
                                                        <option value="USD">US Dollar</option>
                                                        <!-- <option value="AUD">Australlia Dollar</option> -->
                                                        <option value="EUR">Euro</option>
                                                        <option value="GBP">Pound</option>
                                                        <option value="ETB">Ethiopian Birr</option>
                                                    </select>
                                                    <!-- <input type="phone" class="form-control border-0" id="phone" placeholder="Phone"> -->
                                                    <label for="phone">Currency</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-12 col-xl-6">
                                                <div class="form-floating">
                                                    <input type="number" required name="amount" class="form-control border-0" id="amount" placeholder="Amount to donate">
                                                    <label for="amount">Amount</label>
                                                </div>
                                            </div>

                                            <input type="hidden" name="access_key" value="<?php echo $access_key; ?>" >
                                            <input type="hidden" name="profile_id" value="<?php echo $profile_id; ?>" >
                                            <input type="hidden" name="transaction_uuid" value="<?php echo $transaction_uuid; ?>" >
                                            <input type="hidden" name="signed_field_names" value="access_key,profile_id,transaction_uuid,signed_field_names,unsigned_field_names,signed_date_time,locale,transaction_type,reference_number,amount,currency">
                                            <input type="hidden" name="unsigned_field_names" value="">
                                            <input type="hidden" name="signed_date_time" value="<?php echo gmdate("Y-m-d\TH:i:s\Z"); ?>" >
                                            <input type="hidden" name="locale" value="en" >
                                            <input type="hidden" name="transaction_type" value="sale" >
                                            <input type="hidden" name="reference_number" value="<?php echo $reference_number;?>" >

                                            <div class="col-12">
                                                <input type="submit" id="submit" name="submit" class="btn btn-primary w-100 py-3" value="Donate"/>
                                                <!-- <button class="btn btn-primary w-100 py-3">Donate</button> -->
                                            </div>
                                        </div>
                                    </form>

                                    <?php
                                    }
                                ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Contact End -->