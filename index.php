<?php


$host = '*HOST*';
$user = '*USER*';
$password = '*PASSWORD*';
$database = '*DB*';


$connection = new mysqli($host, $user, $password, $database);


if ($connection->connect_error) 
{
    die("Connection failed: " . $connection->connect_error);
} 
else 
{
    echo "Connected successfully to MariaDB! \n";
    echo $connection -> connection_status;
}




function insertUser($conn, $user_id, $user_balance) 
{

    $sql = "INSERT INTO user_info (user_id, user_balance) VALUES (?, ?)";


    if ($stmt = $conn->prepare($sql)) 
    {

        $stmt->bind_param("id", $user_id, $user_balance); 

        if ($stmt->execute()) 
        {
            //successfully logic
        } 
        else 
        {
            //inserting error logic
        }

        $stmt->close();
    } 
    else 
    {
        echo "Error: " . $conn->error;
    }

    return TRUE;
}



function updateUser($connection, $user_id, $user_balance) 
{
    $sql = "UPDATE user_info SET user_balance=? WHERE user_id=?";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("di", $user_balance, $user_id);
    

    if ($stmt->execute()) 
    {
        //successfully update logic
    } 
    else 
    {
        //update error logic
    }
    
    $stmt->close();
}


function deleteUser($connection, $user_id) 
{
    $sql = "DELETE FROM user_info WHERE user_id=?";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) 
    {
        //successfully delete logic
    } 
    else 
    {
        //delete error logic
    }

    $stmt->close();
}


function fetchUser($conn, $user_id) 
{

    $sql = "SELECT user_balance FROM user_info WHERE user_id = ?";

    if ($stmt = $conn->prepare($sql)) {

        $stmt->bind_param("i", $user_id); 

        if ($stmt->execute()) 
        {
            $result = $stmt->get_result();

            if ($result->num_rows > 0) 
            {
                $user = $result->fetch_assoc();
                return $user['user_balance'];
            } 
            else 
            {
                return -1;
            }
        } 
        else 
        {
            //execute error logic
        }

        $stmt->close();
    } 
    else 
    {
        //connection error logic
    }
}


$botToken = "*BOT_TOKEN*";
$apiURL = "https://api.telegram.org/bot$botToken/";


function sendRequest($method, $params = []) 
{
    global $apiURL;

    $url = $apiURL . $method;

    if (!empty($params)) 
    {
        $url .= '?' . http_build_query($params);
    }

    $response = file_get_contents($url);
    return json_decode($response, true);
}






function handleUpdate($update) 
{

    if (!isset($update['message'])) 
    {
        return;
    }

    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    global $connection;
    
    
    if ($text === '/start') 
    {
        if (fetchUser($connection, $chat_id) != -1) 
        {
            sendMessage($chat_id, "Your wallet already created");
            return;
        }
        sendMessage($chat_id, "Your wallet was created");
        sendMessage($chat_id, "/balance - to check balance");
        insertUser($connection, $chat_id, 0);
        return;
    }

    if ($text === '/balance') 
    {
        sendMessage($chat_id, "Balance: ".fetchUser($connection, $chat_id));
        return;
    }


    try 
    {
        $text = str_replace(",", "." , $text);
        $n = doubleval($text);
        echo "N: ".$n."\n";
    } 
    catch (\Throwable $th) 
    {
        sendMessage($chat_id, "Unknown command");
        return;
    }
    

    if ($n >= 0)
    {
        $current_balance = fetchUser($connection, $chat_id);
        updateUser($connection, $chat_id, $current_balance + $n);
        sendMessage($chat_id, "Balance was updated");
        sendMessage($chat_id, "Balance: ".fetchUser($connection, $chat_id))."$";
    }
    else
    {
        $current_balance = doubleval(fetchUser($connection, $chat_id));
        if ($current_balance >= -$n) 
        {
            updateUser($connection, $chat_id, $current_balance + $n);
            sendMessage($chat_id, "Balance was updated");
            sendMessage($chat_id, "Balance: ".fetchUser($connection, $chat_id))."$";
        }
        else 
        {
            sendMessage($chat_id, "Oops! Not enought money");
        }
    }
    

    
}


function sendMessage($chat_id, $text) {
    sendRequest("sendMessage", [
        'chat_id' => $chat_id,
        'text' => $text
    ]);
}


$lastUpdateId = 0;

while (true) {
    $updates = sendRequest("getUpdates", [
        'offset' => $lastUpdateId + 1, // Start fetching from the next update
        'timeout' => 10 // Seconds to wait for a new update
    ]);

    if (!empty($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $lastUpdateId = $update['update_id'];
            handleUpdate($update);
        }
    }

    // Sleep for a second to avoid rate limiting
    sleep(1);
}




$connection->close();
?>


