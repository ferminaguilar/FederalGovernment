<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* modules/contrib/gin_toolbar/templates/toolbar--gin.html.twig */
class __TwigTemplate_33d450973d0c4bc1cb9e538ee96fde94 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 7
        if ((($tmp =  !($context["core_navigation"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 8
            yield "  ";
            try {
                $_v0 = $this->load("@gin/navigation/toolbar--gin.html.twig", 8);
            } catch (LoaderError $e) {
                // ignore missing template
                $_v0 = null;
            }
            if ($_v0) {
                yield from $_v0->unwrap()->yield($context);
            }
        }
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["core_navigation"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/gin_toolbar/templates/toolbar--gin.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  46 => 8,  44 => 7,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/gin_toolbar/templates/toolbar--gin.html.twig", "/Applications/MAMP/htdocs/FederalGovernment/web/modules/contrib/gin_toolbar/templates/toolbar--gin.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 7, "include" => 8];
        static $filters = [];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'include'],
                [],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
