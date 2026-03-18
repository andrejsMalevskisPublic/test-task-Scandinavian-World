 <?php

class PasswordException extends Exception {}

class PasswordModel {
    private $input;
    private $numbers;
    private $smallLetters;
    private $capitalLetters;

    private function getInput() {
        $letters = implode('', range('a', 'z'));
        $numbers = implode('', range(0, 9));

        return $letters . $numbers;
    }

    public function __construct() {

        $this->input = $this->getInput();

        preg_match_all('/\d/', $this->input, $nums);
        preg_match_all('/[a-z]/', $this->input, $letters);

        $this->numbers = array_unique($nums[0]);
        $this->smallLetters = $letters[0];
        $this->capitalLetters = array_map('strtoupper', $letters[0]);

    }


    public function getNumbers() { return $this->numbers; }
    public function getSmallLetters() { return $this->smallLetters; }
    public function getCapitalLetters() { return $this->capitalLetters; }

}

class PasswordService {

    private $model;

    public function __construct($model) {
        $this->model = $model;
    }

    public function generatePassword($length, $useNumbers, $useLower, $useUpper) {

        $selectedSets = [];
        if ($useNumbers) $selectedSets[] = $this->model->getNumbers();
        if ($useLower) $selectedSets[] = $this->model->getSmallLetters();
        if ($useUpper) $selectedSets[] = $this->model->getCapitalLetters();

        if (empty($selectedSets)) {
            throw new PasswordException('At least one set must be selected');
        }

        $passwordChars = [];
        foreach ($selectedSets as $set) {
            $passwordChars[] = $set[array_rand($set)];
        }
        
        $allAvailableChars = [];
        foreach ($selectedSets as $set) {
            $allAvailableChars = array_merge($allAvailableChars, $set);
        }

        $allAvailableChars = array_values(array_unique($allAvailableChars));

        if ($length > count($allAvailableChars)) {
            throw new PasswordException('Not enough unique characters to generate desired length');
        }

        $remainingPool = array_diff($allAvailableChars, $passwordChars);

        shuffle($remainingPool);

        while (count($passwordChars) < $length) {
            $passwordChars[] = array_pop($remainingPool);
        }

        shuffle($passwordChars);

        return implode('', $passwordChars);

    }

}

function renderPasswordForm() {


    $length = 12;
    $useNumbers = true;
    $useLower = true;
    $useUpper = true;
    $password = '';
    $escapedPassword = '';
    $escapedError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $length = (int)($_POST['length'] ?? 12);
        $useNumbers = isset($_POST['use_numbers']);
        $useLower = isset($_POST['use_lowercase']);
        $useUpper = isset($_POST['use_uppercase']);

        $model = new PasswordModel();
        $service = new PasswordService($model);

        try {
            $password = $service->generatePassword($length, $useNumbers, $useLower, $useUpper);
            $escapedPassword = htmlspecialchars($password, ENT_QUOTES | ENT_HTML5);
        } catch (PasswordException $e) {
            $error = $e->getMessage();
            $escapedError = htmlspecialchars($error, ENT_QUOTES | ENT_HTML5);
        }


    }

    $checkedNumbers = $useNumbers ? 'checked' : '';
    $checkedLower = $useLower ? 'checked' : '';
    $checkedUpper = $useUpper ? 'checked' : '';


    echo 

    <<<HTML
        <div class="box">
            <h2>Password Generator</h2>
            <form method="POST">

                <label>Password length:</label>
                <input type="number" id='length' name="length" value="{$length}" min="4" max="64"><br><br>

                <label><input id='use_numbers' type="checkbox" name="use_numbers" {$checkedNumbers}> Include numbers</label><br>
                <label><input id='use_lowercase' type="checkbox" name="use_lowercase" {$checkedLower}> Include lowercase letters</label><br>
                <label><input id='use_uppercase' type="checkbox" name="use_uppercase" {$checkedUpper}> Include uppercase letters</label><br><br>

                <button type="submit">Generate</button>
            </form>

            <div id="password" class="password"></div>
            <input type="hidden" id="generatedPassword" value="{$escapedPassword}">
            <div id="error" class="error">{$escapedError}</div>
        </div>
    HTML;

}

?>

<!DOCTYPE html>

<html>
<head>
<title>Password Generator</title>
<style>
body{
    font-family: Arial;
    background:#f4f4f4;
    padding:40px;
}

.box{
    background:white;
    padding:30px;
    width:400px;
    border-radius:10px;
}

input,button{
    padding:10px;
    font-size:16px;
}

.password{
    margin-top:20px;
    font-size:20px;
    font-weight:bold;
}

.error {
    font-weight:bold;
    color: #ff0000
}

</style>
</head>
<body>

<?php renderPasswordForm(); ?>

</body>
<script>

    function calculateTotalCapacity(length) {
        let poolSize = 0;
        let maxCapacity = 1;
        const useNumbers = document.getElementById('use_numbers').checked;
        const useLowerCase = document.getElementById('use_lowercase').checked;
        const useUpperCase = document.getElementById('use_uppercase').checked;

        if (useNumbers) poolSize += 10;
        if (useLowerCase) poolSize += 26;
        if (useUpperCase) poolSize += 26;

        if (poolSize === 0 || length > poolSize) return 0;
        
        for (let i = 0; i < length; i++) {
            maxCapacity *= (poolSize - i);
        }
        return maxCapacity;
    }

    const passwordDiv = document.getElementById('password');
    const newPass = document.getElementById('generatedPassword')?.value;
    const lengthInput = document.getElementById('length');
    const length = Number(lengthInput?.value) || 12;
    const errorDiv = document.getElementById('error');

    if (newPass) {

        let storageData = localStorage.getItem('my_unique_passwords');
        let data = {};

        try {
            const storageData = localStorage.getItem('my_unique_passwords');

            if (storageData && storageData.startsWith('{')) {
                data = JSON.parse(storageData);
            }
            
        } catch (e) {
            console.warn("LocalStorage corrupted, resetting history.");
            data = {}; 
        }

        if (typeof data !== 'object' || data === null) {
            data = {};
        }
        
        if (!Array.isArray(data[length])) {
            data[length] = [];
        }

        let passwordHistory = data[length];
        const maxCount = calculateTotalCapacity(length);

        if (!passwordHistory.includes(newPass)) {

            passwordDiv.innerText = newPass;
            passwordHistory.push(newPass);

            localStorage.setItem('my_unique_passwords', JSON.stringify(data));
            console.log("New password saved for length " + length);

        } else if (passwordHistory.length < maxCount) {

            console.log("Duplicate found, regenerating...");
            document.querySelector("form").submit();

        } else {

            errorDiv.innerText = 'You have used up all possible combinations for this length!';
        }
    }

</script>
</html> 
