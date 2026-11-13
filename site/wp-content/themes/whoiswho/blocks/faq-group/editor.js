/* global wp */
// Editor side of the FAQ blocks (REQ-260): a group with a heading holds questions; a question has a title and a rich answer.
// Reordering uses the editor's own drag handles and move arrows. Output is rendered by the theme (inc/faq.php).
(function () {
  const { registerBlockType } = wp.blocks;
  const { RichText, InnerBlocks, useBlockProps } = wp.blockEditor;
  const { createElement: el } = wp.element;
  const { __ } = wp.i18n;

  registerBlockType("whoiswho/faq-group", {
    edit: function (props) {
      return el(
        "section",
        useBlockProps({ className: "faq-group" }),
        el(RichText, {
          tagName: "h2",
          className: "faq-group-h",
          value: props.attributes.heading,
          allowedFormats: [],
          placeholder: __("Group heading", "whoiswho"),
          onChange: function (heading) {
            props.setAttributes({ heading: heading });
          },
        }),
        el(InnerBlocks, {
          allowedBlocks: ["whoiswho/faq-question"],
          template: [["whoiswho/faq-question"]],
        })
      );
    },
    save: function () {
      return el(InnerBlocks.Content);
    },
  });

  registerBlockType("whoiswho/faq-question", {
    edit: function (props) {
      return el(
        "div",
        useBlockProps({ className: "faq-q" }),
        el(RichText, {
          tagName: "p",
          className: "faq-q-title",
          value: props.attributes.title,
          allowedFormats: [],
          placeholder: __("Question", "whoiswho"),
          onChange: function (title) {
            props.setAttributes({ title: title });
          },
        }),
        el(InnerBlocks, {
          allowedBlocks: ["core/paragraph", "core/list"],
          template: [["core/paragraph", { placeholder: __("Answer. Link to a page by choosing it in the link box.", "whoiswho") }]],
        })
      );
    },
    save: function () {
      return el(InnerBlocks.Content);
    },
  });
})();
