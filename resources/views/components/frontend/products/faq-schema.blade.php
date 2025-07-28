@php
    $faqQuestions = [];
    foreach($faqs as $faqGroup) {
        foreach($faqGroup->questions as $faq) {
            $faqQuestions[] = [
                '@type' => 'Question',
                'name' => $faq['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'] ?? ''
                ]
            ];
        }
    }
@endphp

<script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": {!! json_encode($faqQuestions) !!}
    }
</script>
