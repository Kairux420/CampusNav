<?php
require_once __DIR__ . '/../config/gemini.php';

function normalizeAssistantText($value)
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim((string) $value);
}

function isCurrentLocationRequest($message)
{
    $messageText = trim((string) $message);
    if ($messageText === '') {
        return false;
    }

    $isWhereAmI = preg_match('/\bwhere am i\b/i', $messageText);
    $isNearPhrase = preg_match('/\b(i\s*am|i\'?m|im)\s+near\b/i', $messageText)
        || preg_match('/\bnear(?:by)?\b/i', $messageText)
        || preg_match('/\baround here\b/i', $messageText);

    $isNavigationHint = preg_match('/\b(go to|take me to|navigate to|find|show me|get to|head to|route me)\b/i', $messageText);

    return $isWhereAmI || ($isNearPhrase && !$isNavigationHint);
}

function findBestRoomMatch($message, $availableRooms)
{
    $rooms = is_array($availableRooms) ? $availableRooms : [];
    $roomList = array_values(array_unique(array_filter(array_map(function ($room) {
        return trim((string) $room);
    }, $rooms), function ($room) {
        return $room !== '';
    })));

    if ($roomList === []) {
        return null;
    }

    $normalizedMessage = normalizeAssistantText($message);
    $bestMatch = null;
    $bestScore = 0;

    foreach ($roomList as $room) {
        $roomName = trim((string) $room);
        $roomKey = normalizeAssistantText($roomName);
        $score = 0;

        if ($roomKey === $normalizedMessage) {
            $score = 100;
        } elseif ($normalizedMessage !== '' && strpos($normalizedMessage, $roomKey) !== false) {
            $score = 90;
        } elseif ($roomKey !== '' && strpos($roomKey, $normalizedMessage) !== false) {
            $score = 80;
        } else {
            $messageTokens = $normalizedMessage === '' ? [] : array_values(array_filter(explode(' ', $normalizedMessage)));
            $roomTokens = $roomKey === '' ? [] : array_values(array_filter(explode(' ', $roomKey)));
            $commonTokens = count(array_intersect($messageTokens, $roomTokens));
            if ($commonTokens > 0) {
                $score = 50 + $commonTokens;
            }
        }

        if ($score >= 65 && $score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $roomName;
        }
    }

    return $bestMatch;
}

function shouldAskForCurrentLocation($message, $destination, $availableRooms)
{
    if ($destination === null) {
        return false;
    }

    $messageText = trim((string) $message);
    $normalizedMessage = strtolower($messageText);
    if (!preg_match('/\b(nearest|closest|nearby)\b/i', $normalizedMessage)) {
        return false;
    }

    $normalizedDestination = normalizeAssistantText($destination);
    if ($normalizedDestination === '') {
        return false;
    }

    $nameCounts = array_count_values(array_map('normalizeAssistantText', $availableRooms));
    return isset($nameCounts[$normalizedDestination]) && $nameCounts[$normalizedDestination] > 1;
}

function getFallbackAssistantResponse($message, $availableRooms)
{
    $messageText = trim((string) $message);
    $normalizedMessage = normalizeAssistantText($messageText);
    $bestRoom = findBestRoomMatch($messageText, $availableRooms);

    $isWhereAmIRequest = preg_match('/\b(where am i|what wing am i|what floor am i|where is this|where are we)\b/i', $messageText);
    $isNavigationRequest = preg_match('/(go to|take me to|find|show me|where is|where\'s|navigate to|room|classroom|lab|get to|head to|can you take me)/i', $messageText);
    $isFacilityRequest = preg_match('/(bathroom|toilet|restroom|washroom|stairs|lift|elevator|entrance|exit|surau|prayer|cafeteria|auditorium)/i', $messageText);

    if ($isWhereAmIRequest) {
        $nearbyRoom = null;
        if (preg_match('/\b(near|close to|next to|by|around|nearby)\b/i', $messageText)) {
            $nearbyRoom = findBestRoomMatch($messageText, $availableRooms);
        }

        return [
            'success' => true,
            'intent' => 'info',
            'destination' => null,
            'reply' => $nearbyRoom !== null
                ? 'It sounds like you are near ' . $nearbyRoom . '. Would you like to stay here, or do you want help finding a different room?'
                : 'I cannot determine your current location from this chat alone. Please tell me where you are now, such as a room name or nearby landmark, so I can help you navigate.'
        ];
    }

    if ($bestRoom !== null && shouldAskForCurrentLocation($messageText, $bestRoom, $availableRooms)) {
        return [
            'success' => true,
            'intent' => 'info',
            'destination' => null,
            'reply' => 'I found multiple possible matches for that facility. Please tell me where you are now or a nearby room so I can choose the best route.'
        ];
    }

    if ($bestRoom !== null && $isNavigationRequest) {
        return [
            'success' => true,
            'intent' => 'navigate',
            'destination' => $bestRoom,
            'reply' => 'I can help you get to ' . $bestRoom . '.'
        ];
    }

    if (preg_match('/bathroom|toilet|restroom|washroom/i', $normalizedMessage)) {
        $restroomRoom = null;
        foreach ($availableRooms as $room) {
            if (normalizeAssistantText($room) === 'restroom') {
                $restroomRoom = $room;
                break;
            }
        }

        return [
            'success' => true,
            'intent' => $restroomRoom !== null ? 'navigate' : 'info',
            'destination' => $restroomRoom,
            'reply' => $restroomRoom !== null
                ? 'I can help you get to the nearest restroom at ' . $restroomRoom . '.'
                : 'I can help you find a restroom. Try searching the map for the nearest facility directly.'
        ];
    }

    if (preg_match('/stairs|lift|elevator|entrance|exit|surau|prayer|cafeteria|auditorium/i', $normalizedMessage) && $bestRoom !== null && $isFacilityRequest) {
        return [
            'success' => true,
            'intent' => 'navigate',
            'destination' => $bestRoom,
            'reply' => 'I can help you get to ' . $bestRoom . '.'
        ];
    }

    if ($bestRoom !== null && $isFacilityRequest) {
        return [
            'success' => true,
            'intent' => 'navigate',
            'destination' => $bestRoom,
            'reply' => 'I can help you get to ' . $bestRoom . '.'
        ];
    }

    return [
        'success' => true,
        'intent' => 'info',
        'destination' => null,
        'reply' => 'I could not match that to a known room. Try asking for a specific room name from the map.'
    ];
}

function askGemini($userMessage, $availableRooms)
{
    $message = trim((string) $userMessage);

    if ($message === '') {
        return [
            'success' => false,
            'error' => 'Please enter a message.'
        ];
    }

    $rooms = is_array($availableRooms) ? $availableRooms : [];
    $roomList = array_values(array_filter(array_map(function ($room) {
        return trim((string) $room);
    }, $rooms), function ($room) {
        return $room !== '';
    }));
    $uniqueRoomList = array_values(array_unique($roomList));

    if (!defined('GEMINI_API_KEY') || !GEMINI_API_KEY) {
        return getFallbackAssistantResponse($message, $roomList);
    }

    $prompt = "You are CampusNav AI, the highly intelligent and friendly indoor navigation assistant for the FCVAC university building. " .
        "Act as a sophisticated, warm, and extremely helpful AI. You have deep knowledge of the campus layout. " .
        "IMPORTANT RULES & DOMAIN KNOWLEDGE: " .
        "1. Prayer rooms are officially named 'Surau'. If someone asks for a prayer room, musolla, or surau, route them to 'Surau'. " .
        "2. Toilets and washrooms are officially named 'Restroom'. If asked for a toilet or bathroom, route them to 'Restroom'. " .
        "3. If they ask for a general category (like 'Computer Labs' or 'Lecturer Offices') instead of a specific room, explain they can use the 'Category (Tag)' dropdown on the Search Page to filter all rooms of that type! " .
        "4. Return ONLY valid JSON with this exact structure: {\"intent\": \"navigate\" or \"info\", \"destination\": \"<exact room name from list, or null>\", \"reply\": \"<your conversational AI response>\"}. " .
        "5. Where Am I: If asked for their current location, wing, or floor (e.g. 'where am I', 'what wing am I', 'what floor am I in'), set intent to 'info', destination to null, and explain you don't have indoor GPS tracking, so they need to tell you a nearby room code first. " .
        "6. Navigation: If they ask for directions to a specific room or facility, set intent to 'navigate' and set destination to the EXACT matching room name from the list. " .
        "Do not invent rooms that are not in the provided list. If you can't find it, ask them to clarify. " .
        "The user's message: $message. " .
        "Available rooms: " . implode(', ', $uniqueRoomList);

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ]]
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . urlencode(GEMINI_API_KEY);
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        return getFallbackAssistantResponse($message, $roomList);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return getFallbackAssistantResponse($message, $roomList);
    }

    if (!empty($decoded['error'])) {
        return getFallbackAssistantResponse($message, $roomList);
    }

    $text = '';
    if (!empty($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        $text = trim((string) $decoded['candidates'][0]['content']['parts'][0]['text']);
    }

    if ($text === '') {
        return getFallbackAssistantResponse($message, $roomList);
    }

    $cleanText = $text;
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches)) {
        $cleanText = trim($matches[1]);
    }

    $data = json_decode($cleanText, true);
    if (!is_array($data)) {
        return getFallbackAssistantResponse($message, $roomList);
    }

    $intent = isset($data['intent']) ? strtolower((string) $data['intent']) : 'info';
    if (!in_array($intent, ['navigate', 'info'], true)) {
        $intent = 'info';
    }

    $destination = isset($data['destination']) && $data['destination'] !== null
        ? trim((string) $data['destination'])
        : null;
    $reply = isset($data['reply']) ? trim((string) $data['reply']) : '';

    if ($intent === 'navigate' && $destination !== null && shouldAskForCurrentLocation($message, $destination, $roomList)) {
        return [
            'success' => true,
            'intent' => 'info',
            'destination' => null,
            'reply' => 'I found multiple possible matches for that facility. Please tell me where you are now or a nearby room so I can choose the best route.'
        ];
    }

    return [
        'success' => true,
        'intent' => $intent,
        'destination' => $destination,
        'reply' => $reply
    ];
}
