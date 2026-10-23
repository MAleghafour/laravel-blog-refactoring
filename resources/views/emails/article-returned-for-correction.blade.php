<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>درخواست اصلاح مقاله</title>
</head>
<body>
<h2>مقاله شما نیازمند اصلاح است</h2>

<p>عنوان مقاله: {{ $articleTitle }}</p>
<p>بررسی‌کننده: {{ $actorName }}</p>
<p>تاریخ بررسی: {{ $occurredAt }}</p>
<p>دلیل بازگشت: {{ $explanation }}</p>

<a href="{{ route('article.edit', $articleId) }}">
    ویرایش مقاله در Dashboard
</a>
</body>
</html>
