((blocks, element, serverSideRender, blockEditor) =>
{
  let elem = element.createElement,
    registerBlockType = blocks.registerBlockType,
    ServerSideRender = serverSideRender,
    useBlockProps = blockEditor.useBlockProps;

  registerBlockType('trader/portfolio', {
    apiVersion: 2,

    edit: props =>
    {
      return elem(
        'div',
        useBlockProps(),
        elem(ServerSideRender, {
          block: 'trader/portfolio',
          attributes: props.attributes,
        })
      );
    },
    save: () => null,
  });
})(
  window.wp.blocks,
  window.wp.element,
  window.wp.serverSideRender,
  window.wp.blockEditor
);