<?php

return [
    'access_token_length' => (int) env('API_ACCESS_TOKEN_LENGTH', 60),
    'token_length' => (int) env('API_TOKEN_LENGTH', 32),
    'user_token_length' => (int) env('API_USER_TOKEN_LENGTH', 40),
    
    'max_image_upload_size' => (int) env('MAX_IMAGE_UPLOAD_SIZE', 15728640), // 15MB
    'max_video_upload_size' => (int) env('MAX_VIDEO_UPLOAD_SIZE', 524288000), // 500MB
    'max_image_thumbnail_upload_size' => (int) env('MAX_IMAGE_THUMBNAIL_UPLOAD_SIZE', 5242880), // 5MB
    'max_image_avatar_upload_size' => (int) env('MAX_IMAGE_AVATAR_UPLOAD_SIZE', 5242880), // 5MB
    'max_image_logo_upload_size' => (int) env('MAX_IMAGE_LOGO_UPLOAD_SIZE', 5242880), // 5MB
    'max_unknown_upload_size' => (int) env('MAX_UNKNOWN_UPLOAD_SIZE', 5242880), // 5MB
];
