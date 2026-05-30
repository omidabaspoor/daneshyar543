import re

with open('api/chat.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_stream = """DaneshyarAI::streamChat($messages, $imageBase64, $imageMime, function($chunk) use (&$fullReply) {
    $fullReply .= $chunk;
    
    $lines = explode("\\n", $chunk);
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0) {
            $dataStr = substr($line, 6);
            if (trim($dataStr) === '[DONE]') continue;
            
            $json = json_decode($dataStr, true);
            if (isset($json['choices'][0]['delta']['content'])) {
                $text = $json['choices'][0]['delta']['content'];
                echo "data: " . json_encode(['chunk' => $text], JSON_UNESCAPED_UNICODE) . "\\n\\n";
                ob_flush();
                flush();
            }
        }
    }
});"""

new_stream = """$buffer = '';
$fullReplyText = '';
DaneshyarAI::streamChat($messages, $imageBase64, $imageMime, function($chunk) use (&$buffer, &$fullReplyText) {
    $buffer .= $chunk;
    while (($pos = strpos($buffer, "\\n\\n")) !== false) {
        $message_str = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 2);
        
        $lines = explode("\\n", $message_str);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $dataStr = substr($line, 6);
                if (trim($dataStr) === '[DONE]') continue;
                
                $json = json_decode($dataStr, true);
                if (isset($json['choices'][0]['delta']['content'])) {
                    $text = $json['choices'][0]['delta']['content'];
                    $fullReplyText .= $text;
                    echo "data: " . json_encode(['chunk' => $text], JSON_UNESCAPED_UNICODE) . "\\n\\n";
                    ob_flush();
                    flush();
                }
            }
        }
    }
});"""

content = content.replace(old_stream, new_stream)

old_save = """// Save the final AI reply to the database
if (!empty($fullReply)) {
    // we need to strip the SSE wrappers to save just the content
    $cleanContent = '';
    $lines = explode("\\n", $fullReply);
    foreach($lines as $line) {
        if (strpos($line, 'data: ') === 0) {
            $json = json_decode(substr($line, 6), true);
            if (isset($json['choices'][0]['delta']['content'])) {
                $cleanContent .= $json['choices'][0]['delta']['content'];
            }
        }
    }
    db()->prepare("INSERT INTO chat_history (user_id, chat_id, book_id, role, content) VALUES (?,?,?, 'assistant', ?)")
        ->execute([$user['id'], $chat_id, $book ? (int)$book['id'] : null, $cleanContent]);
}"""

new_save = """// Save the final AI reply to the database
if (!empty($fullReplyText)) {
    db()->prepare("INSERT INTO chat_history (user_id, chat_id, book_id, role, content) VALUES (?,?,?, 'assistant', ?)")
        ->execute([$user['id'], $chat_id, $book ? (int)$book['id'] : null, $fullReplyText]);
}"""

content = content.replace(old_save, new_save)

with open('api/chat.php', 'w', encoding='utf-8') as f:
    f.write(content)
