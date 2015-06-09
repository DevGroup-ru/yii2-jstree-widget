<?php

namespace devgroup\JsTreeWidget;

use Yii;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;


class ContextMenuHelper
{

    /**
     * Returns JavaScript expression(\yii\web\JsExpression) for context menu item.
     * Used for redirecting browser to node-specific action(ie. edit, delete).
     * Automatically appends needed $.data() attributes(can be specified in HTML5 data attribute format too).
     *
     * @param array $route Action route
     * @param bool|array $dataAttributes Array of attributes to append, true to append all attributes, false if you don't want to append any data-attributes.
     * @return JsExpression
     */
    public static function actionUrl(array $route, $dataAttributes = true)
    {
        $baseUrl = Json::encode(Url::to($route));
        $union = strpos($baseUrl, '?') > 0 ? '&' : '?';

        $dataExpression = "var data = \$object.data(), dataVariables = [];";

        if (is_array($dataAttributes) === true) {
            // only selected set of attributes
            foreach ($dataAttributes as $attribute => $match) {
                if (is_numeric($attribute) === true) {
                    $attribute = $match;
                }
                $jsonAttribute = Json::encode($attribute);
                $matchAttribute = Json::encode($match);

                $dataExpression .= "
                if (typeof(data[$matchAttribute]) !== 'undefined') {
                    dataVariables.push( '$attribute=' + encodeURIComponent(data[$matchAttribute]) );
                }\n";

            }
        } elseif ($dataAttributes === true) {
            // all attributes
            $dataExpression .= "
            for (var attributeName in data) {
                dataVariables.push(encodeURIComponent(attributeName) + '=' + encodeURIComponent(data[attributeName]));
            };\n";
        } else {
            $dataExpression = "var dataVariables = '';";
        }
        $dataExpression .= "dataVariables=dataVariables.join('&'); ";
        return new JsExpression(
            "function(node) {
                var \$object = node.reference ? \$(node.reference[0]) : node;
                $dataExpression
                document.location = $baseUrl + '$union' + dataVariables;
                return false;
            }"
        );
    }
} 