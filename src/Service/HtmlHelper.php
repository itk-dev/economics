<?php

namespace App\Service;

class HtmlHelper
{
    /**
     * Get section in HTML document identified by heading content.
     */
    public function getSection(string $html, string $title, string $tagName = 'h3', bool $useRegex = false, bool $includeHeading = false): ?string
    {
        $dom = new \DOMDocument();
        $wrapperId = 'html-helper-wrapper';
        $dom->loadHTML('<div id="'.$wrapperId.'">'.$html.'</div>');
        /** @var \DOMElement $wrapper */
        $wrapper = $dom->getElementById($wrapperId);

        $headings = $dom->getElementsByTagName($tagName);

        /** @var \DOMElement $element */
        foreach (iterator_to_array($headings) as $index => $element) {
            if ($element->textContent === $title
                || ($useRegex && !empty($title) && preg_match($title, $element->textContent))) {
                // The node list is live, so we must remove trailing content before removing leading content.
                if ($nextElement = $headings->item($index + 1)) {
                    assert(!empty($nextElement->parentNode));
                    while ($nextElement->nextSibling) {
                        $nextElement->parentNode->removeChild($nextElement->nextSibling);
                    }
                    $nextElement->parentNode->removeChild($nextElement);
                }

                // Remove leading content.
                assert(!empty($element->parentNode));
                while ($element->previousSibling) {
                    $element->parentNode->removeChild($element->previousSibling);
                }
                if (!$includeHeading) {
                    $element->parentNode->removeChild($element);
                }

                return join(
                    '',
                    array_map(
                        static fn (\DOMNode $node) => $dom->saveHTML($node),
                        iterator_to_array($wrapper->childNodes)
                    )
                );
            }
        }

        return null;
    }

    public function element2separator(string $html, string $elementName, string $before, string $after): string
    {
        $html = trim($html);
        // Remove element start tag at start of string
        $html = preg_replace('@^<'.preg_quote($elementName, '@').'[^>]*>@', '', $html);
        // Remove element end tag at end of string
        $html = preg_replace('@</'.preg_quote($elementName, '@').'>$@', '', $html);

        $placeholder = '[[['.uniqid().']]]';

        // Replace element start tag
        $html = preg_replace('@<'.preg_quote($elementName, '@').'[^>]*>@', $placeholder, $html);
        // Replace multiple consecutive placeholders with a single placeholder.
        $html = preg_replace('@('.preg_quote($placeholder, '@').'){2,}@', $placeholder, $html);
        $html = str_replace($placeholder, $before, $html);

        // Replace element start tag
        $html = preg_replace('@</'.preg_quote($elementName, '@').'>@', $placeholder, $html);
        // Replace multiple consecutive placeholders with a single placeholder.
        $html = preg_replace('@('.preg_quote($placeholder, '@').'){2,}@', $placeholder, $html);
        $html = str_replace($placeholder, $after, $html);

        return $html;
    }
}
