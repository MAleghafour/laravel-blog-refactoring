<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ویرایش مقاله</title>
</head>
<body>
<h2>مقاله شما توسط مدیر ویرایش شد</h2>

<p>عنوان مقاله: {{ $articleTitle }}</p>
<p>ویرایش‌کننده: {{ $actorName }}</p>
<p>تاریخ ویرایش: {{ $occurredAt }}</p>
<p>توضیح مدیر: {{ $explanation }}</p>

<a href="{{ route('article.show', $articleId) }}">
    مشاهده مقاله در Dashboard
</a>
</body>
</html>
