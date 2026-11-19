{{-- A comment's text: escaped, line breaks kept, @mentions shown as name chips (yours highlighted). --}}
@props(['text'])
<p {{ $attributes->merge(['class' => 'small mb-1 comment-text']) }}>{{ \App\Support\MentionParser::highlight($text, auth()->user()) }}</p>
