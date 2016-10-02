?php

namespace Layout\Core\Block;

class Html extends \Layout\Core\Block
{
	/**
     * Get relevant path to template.
     *
     * @return string
     */
    public function getTemplate()
    {
        $fileLocation = $this->config->get('handle_layout_section');
        $template = "$fileLocation.{$template}";
        return $template;
    }

    protected function getView($fileName, $viewVars)
    {
    	return $html;
    }
}