import re

with open('api/chat.php', 'r', encoding='utf-8') as f:
    content = f.read()

old = """DaneshyarAI::streamChat($messages, $imageBase64, $imageMime, function($chunk) use (&$fullReply) {
    $fullReply .= $chunk;
    
    // OpenAI SSE format: "data: {...}\\n\\n"
    if (preg_match('/data: (\\{.*?\\})/', $chunk, $matches)) {
        $json = json_decode($matches[1], true);
        if (isset($json['choices'][0]['delta']['content'])) {
            $text = $json['choices'][0]['delta']['content'];
            echo "data: " . json_encode(['chunk' => $text], JSON_UNESCAPED_UNICODE) . "\\n\\n";
            ob_flush();
            flush();
        }
    }
});"""

new = """DaneshyarAI::streamChat($messages, $imageBase64, $imageMime, function($chunk) use (&$fullReply) {
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

content = content.replace(old, new)

with open('api/chat.php', 'w', encoding='utf-8') as f:
    f.write(content)
