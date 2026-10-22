<?php

declare(strict_types=1);

namespace Topics\News;

use Topics\News\Contracts\NewsViewInterface;

class NewsView implements NewsViewInterface
{
    /**
     * @see NewsViewInterface::renderStories()
     *
     * @param array<int, array{aid: string, informant: string, time: int|string, title: string, counter: int, topic: int, hometext: ?string, notes: string, morelink: string, topicname: string, topicimage: string, topictext: string}> $stories
     */
    public function renderStories(array $stories): void
    {
        foreach ($stories as $vm) {
            themeindex(
                $vm['aid'],
                $vm['informant'],
                $vm['time'],
                $vm['title'],
                $vm['counter'],
                $vm['topic'],
                $vm['hometext'],
                $vm['notes'],
                $vm['morelink'],
                $vm['topicimage'],
                $vm['topictext'],
            );
        }
    }

    /**
     * @see NewsViewInterface::renderArticle()
     *
     * @param array{aid: string, informant: string, time: int|string, title: string, bodytext: ?string, topic: int, topicname: string, topicimage: string, topictext: string} $vm
     */
    public function renderArticle(array $vm): void
    {
        themearticle(
            $vm['aid'],
            $vm['informant'],
            $vm['time'],
            $vm['title'],
            $vm['bodytext'],
            $vm['topic'],
            $vm['topicimage'],
            $vm['topictext'],
        );
    }

    /**
     * @see NewsViewInterface::renderPageTitle()
     */
    public function renderPageTitle(bool $isHomeFile): string
    {
        if ($isHomeFile) {
            return '<h1 class="ibl-title">IBL: Internet Basketball League</h1>';
        }

        return '<h1 class="ibl-title">News</h1>';
    }

    /**
     * @see NewsViewInterface::renderTopicBanner()
     */
    public function renderTopicBanner(string $siteName, ?string $safeTopicTitle, int $topicId, string $tableBgcolor1, string $tableBgcolor2): string
    {
        $html = "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\" bgcolor=\"$tableBgcolor2\"><tr><td>\n"
            . "<table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"8\" bgcolor=\"$tableBgcolor1\"><tr><td>\n";

        if ($safeTopicTitle === null) {
            $html .= "<center><font class=\"title\">$siteName</font><br><br>" . _NOINFO4TOPIC . "<br><br>[ <a href=\"modules.php?name=News\">" . _GOTONEWSINDEX . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
        } else {
            $html .= "<center><font class=\"title\">$siteName: $safeTopicTitle</font><br><br>"
                . "<form action=\"modules.php?name=Search\" method=\"post\">"
                . "<input type=\"hidden\" name=\"topic\" value=\"$topicId\">"
                . "" . _SEARCHONTOPIC . ": <input type=\"name\" name=\"query\" size=\"30\">&nbsp;&nbsp;"
                . "<input type=\"submit\" value=\"" . _SEARCH . "\">"
                . "</form>"
                . "[ <a href=\"index.php\">" . _GOTOHOME . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
        }

        return $html . "</td></tr></table></td></tr></table>\n" . "<br>";
    }
}
