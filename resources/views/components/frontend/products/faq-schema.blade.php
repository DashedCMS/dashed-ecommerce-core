@php
    $faqQuestions = [];
    foreach($faqs ?? [] as $faqGroup) {
        foreach($faqGroup->questions ?: [] as $faq) {
            $faqQuestions[] = [
                '@type' => 'Question',
                'name' => $faq['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim(strip_tags(cms()->convertToHtml($faq['answer'] ?? '')))
                ]
            ];
        }
    }
@endphp

@if(count($faqQuestions))
    <script type="application/ld+json">
        @json([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqQuestions
        ])
    </script>
@endif
