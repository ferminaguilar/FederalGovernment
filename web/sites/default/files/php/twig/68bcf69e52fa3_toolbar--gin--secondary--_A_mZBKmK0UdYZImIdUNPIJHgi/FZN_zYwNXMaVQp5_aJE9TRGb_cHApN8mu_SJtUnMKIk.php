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

/* @gin_toolbar/toolbar--gin--secondary--frontend.html.twig */
class __TwigTemplate_8e74f71d91a4c0535f9671ce0fb96e86 extends Template
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
        // line 1
        yield "<div class=\"gin-secondary-toolbar gin-secondary-toolbar--frontend\" data-offset-top>
  <div class=\"gin-secondary-toolbar__layout-container\">
    <div class=\"gin-breadcrumb-wrapper\">
      <div class=\"region-breadcrumb\">
        <nav class=\"gin-breadcrumb\" role=\"navigation\" aria-labelledby=\"gin-system-breadcrumb\">
          <h2 id=\"gin-system-breadcrumb\" class=\"visually-hidden\">";
        // line 6
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Breadcrumb"));
        yield "</h2>
          <ol class=\"gin-breadcrumb__list\">
            <li class=\"gin-breadcrumb__item\">
              ";
        // line 9
        if ((($context["entity_edit_url"] ?? null) && ($context["entity_title"] ?? null))) {
            // line 10
            yield "                <a class=\"gin-breadcrumb__link gin-back-to-admin\" href=\"";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["entity_edit_url"] ?? null), "html", null, true);
            yield "\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Edit %title", ["%title" => ($context["entity_title"] ?? null)]));
            yield "</a>
              ";
        } elseif ((($tmp =         // line 11
($context["admin_content_url"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 12
            yield "                ";
            // line 13
            yield "                <a class=\"gin-breadcrumb__link gin-back-to-admin\" href=\"";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["admin_content_url"] ?? null), "html", null, true);
            yield "\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Back to Administration"));
            yield "</a>
              ";
        }
        // line 15
        yield "            </li>
          </ol>
        </nav>
      </div>
    </div>
    ";
        // line 20
        if ((($tmp = ($context["core_navigation"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 21
            yield "      ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["gin_secondary_toolbar"] ?? null), "html", null, true);
            yield "
    ";
        } else {
            // line 23
            yield "      ";
            try {
                $_v0 = $this->load("@gin/navigation/toolbar--gin--secondary.html.twig", 23);
            } catch (LoaderError $e) {
                // ignore missing template
                $_v0 = null;
            }
            if ($_v0) {
                yield from $_v0->unwrap()->yield($context);
            }
            // line 24
            yield "    ";
        }
        // line 25
        yield "  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["entity_edit_url", "entity_title", "admin_content_url", "core_navigation", "gin_secondary_toolbar"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@gin_toolbar/toolbar--gin--secondary--frontend.html.twig";
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
        return array (  107 => 25,  104 => 24,  93 => 23,  87 => 21,  85 => 20,  78 => 15,  70 => 13,  68 => 12,  66 => 11,  59 => 10,  57 => 9,  51 => 6,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@gin_toolbar/toolbar--gin--secondary--frontend.html.twig", "/Applications/MAMP/htdocs/FederalGovernment/web/modules/contrib/gin_toolbar/templates/toolbar--gin--secondary--frontend.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 9, "include" => 23];
        static $filters = ["t" => 6, "escape" => 10];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'include'],
                ['t', 'escape'],
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
