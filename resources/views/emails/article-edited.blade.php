<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اطلاع‌رسانی ویرایش مقاله</title>
</head>
<body>
<h2>مقاله شما توسط مدیر ویرایش شد</h2>

<p>
    عنوان مقاله: {{ $articleTitle }}
</p>

<p>توضیح مدیر:</p>

<p>{{ $explanation }}</p>

<p>
    <a href="{{ route('article.show', $articleId) }}">
        مشاهده مقاله
    </a>
</p>
</body>
</html>
