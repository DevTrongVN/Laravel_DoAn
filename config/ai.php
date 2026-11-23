<?php

return [
    'provider' => env('AI_PROVIDER', 'gemini'),
    'api_key' => env('GEMINI_API_KEY', env('AI_API_KEY', '')),
    'model' => env('AI_MODEL', 'gemini-3.5-flash-lite'),
    'fallback_models' => [
        'gemini-3.5-flash-lite',
        'gemini-3.5-flash',
        'gemini-3.1-flash-lite',
        'gemini-3.8-flash',
        'gemini-flash-latest',
    ],
    'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
    'moderation' => [
        'auto_scan' => true,
        'flag_threshold' => 0.6,
        'block_threshold' => 0.85,
        'keywords_by_severity' => [
            'low' => [
                'type' => 'inappropriate',
                'suggested_action' => 'flag',
                'description' => 'Từ ngữ công kích nhẹ, thiếu chuẩn mực',
                'words' => [
                    'ngu ngốc', 'vớ vẩn', 'trò hề', 'rác rưởi', 'đồ ngốc', 'đần độn', 'hãm',
                    'ngu xuẩn', 'bẩn thỉu', 'vô dụng', 'kém cỏi', 'đần dốt', 'dở hơi', 'dốt nát',
                    'vô lại', 'ngớ ngẩn', 'xàm xí', 'nhảm nhí', 'vô học', 'óc bã đậu', 'mất dạy',
                    'não ngắn', 'đồ ngu', 'ngu si', 'hãm lìn', 'xàm lìn',
                ],
            ],
            'medium' => [
                'type' => 'harassment',
                'suggested_action' => 'flag',
                'description' => 'Xúc phạm nặng, ngôn từ thù ghét, spam quảng cáo',
                'words' => [
                    'chó chết', 'khốn nạn', 'đồ chó', 'súc vật', 'đĩ', 'mẹ mày', 'thằng chó', 'con chó',
                    'thất đức', 'đồ lợn', 'bệnh hoạn', 'chết tiệt', 'cút xéo', 'chém chết', 'đâm chết',
                    'kiếm tiền online', 'bắn cá đổi thưởng', 'tải app nhận tiền', 'nhóm kín zalo',
                    'kèo bóng đá', 'inbox nhận lộc', 'hoa hồng cao ngất', 'nạp rút tự động', 'cộng tác viên shopee',
                ],
            ],
            'high' => [
                'type' => 'illegal',
                'suggested_action' => 'block',
                'description' => 'Lừa đảo, cờ bạc, cá độ, nội dung độc hại hoặc nguy hiểm',
                'words' => [
                    'lừa đảo', 'đánh bạc', 'tự tử', 'khủng bố', 'phản động', 'cá độ bóng đá',
                    'đá gà trực tiếp', 'casino trực tuyến', 'xóc đĩa đổi thưởng', 'tài xỉu online',
                    'lô đề online', 'mua bán ma túy', 'chất cấm', 'thuốc kích dục', 'vũ khí cấm',
                    'chế tạo bom', 'ấu dâm', 'rửa tiền', 'hack tài khoản',
                ],
            ],
        ],
        'banned_keywords' => [
            'lừa đảo', 'đánh bạc', 'tự tử', 'khủng bố', 'chửi bới', 'xúc phạm', 'phản động',
            'ngu ngốc', 'vớ vẩn', 'trò hề', 'rác rưởi', 'đồ ngốc', 'chó chết', 'khốn nạn',
        ],
    ],
    'system_prompts' => [
        'spellcheck'   => "Bạn là chuyên gia hiệu đính tiếng Việt. Hãy kiểm tra và sửa toàn bộ lỗi chính tả, lỗi dấu thanh, lỗi viết hoa, dấu câu và lặp từ của văn bản sau. Giữ nguyên văn phong, bố cục và ý nghĩa ban đầu của tác giả. CHỈ trả về duy nhất văn bản đã sửa, không thêm lời chào, không giải thích, không markdown code block.",
        'natural'      => "Bạn là biên tập viên tiếng Việt giàu kinh nghiệm. Hãy viết lại văn bản sau sao cho câu văn rõ ràng, trôi chảy, tự nhiên và dễ đọc hơn đối với độc giả, đồng thời giữ nguyên ý nghĩa cốt lõi ban đầu. CHỈ trả về duy nhất văn bản đã viết lại, không thêm lời chào hay giải thích.",
        'concise'      => "Bạn là một biên tập viên cô đọng ngôn từ sắc bén. Hãy rút gọn văn bản sau theo phong cách ngắn gọn, súc tích, đi thẳng vào trọng tâm vấn đề. Cắt bỏ triệt để từ đệm rườm rà và ý lặp, giảm độ dài xuống khoảng 55–75% bản gốc mà vẫn giữ trọn ý chính. CHỈ trả về duy nhất nội dung đã rút gọn, không thêm lời chào hay giải thích.",
        'expand'       => "Bạn là tác giả nội dung chuyên sâu. Hãy mở rộng và làm phong phú văn bản sau bằng cách diễn giải rõ hơn các ý chính, bổ sung ví dụ minh họa hoặc liên kết mạch lạc giữa các câu sao cho bài viết có chiều sâu hơn nhưng vẫn bám sát hoàn toàn chủ đề và quan điểm gốc của tác giả. CHỈ trả về duy nhất văn bản đã mở rộng, không thêm lời chào hay giải thích.",
        'improve'      => "Bạn là biên tập viên nội dung. Hãy cải thiện cách diễn đạt, nâng cao độ mạch lạc, chính xác về từ ngữ và nhịp câu cho văn bản sau nhưng vẫn giữ nguyên ý nghĩa và thông điệp ban đầu của tác giả. CHỈ trả về duy nhất văn bản đã cải thiện, không thêm lời chào hay giải thích.",
        'professional' => "Bạn là một biên tập viên cao cấp. Hãy viết lại toàn diện cấu trúc câu, từ vựng và nhịp điệu của văn bản sau theo phong cách chuyên nghiệp, trang trọng, mạch lạc và giàu tính chuyên môn. Chuẩn hóa đại từ nhân xưng thành 'tôi' hoặc 'chúng ta', nâng cấp từ ngữ đời thường sang thuật ngữ chuẩn mực công sở/học thuật. Giữ trọn vẹn ý chính cốt lõi. CHỈ trả về duy nhất nội dung đã viết lại, không thêm lời chào hay giải thích.",
        'literary'     => "Bạn là một tác giả tản văn tinh tế. Hãy viết lại văn bản sau theo phong cách văn chương, sâu lắng, giàu hình ảnh gợi cảm và cảm xúc tinh tế, phù hợp với một trang blog cá nhân chiêm nghiệm. Giữ trọn vẹn ý chính cốt lõi. CHỈ trả về duy nhất nội dung đã viết lại, không thêm lời chào hay giải thích.",
        'friendly'     => "Bạn là người viết blog gần gũi, thân thiện. Hãy viết lại văn bản sau theo giọng văn ấm áp, cởi mở, tự nhiên như đang trò chuyện chân thành với bạn đọc nhưng vẫn lịch sự và đúng chuẩn tiếng Việt. Giữ trọn vẹn ý chính cốt lõi. CHỈ trả về duy nhất nội dung đã viết lại, không thêm lời chào hay giải thích.",
    ],
    'prompts' => [
        'style_improvement' => "Hãy đọc đoạn văn dưới đây và chỉnh sửa lại câu từ cho trau chuốt, mạch lạc, đúng chính tả và ngắt câu hợp lý mà vẫn giữ nguyên ý nghĩa cốt lõi của tác giả:\n\n{content}",
        'moderation_scan' => "Hãy phân tích nội dung sau và trả về JSON gồm status ('safe' | 'flagged' | 'blocked') và reason:\n\n{content}",
    ],
];
