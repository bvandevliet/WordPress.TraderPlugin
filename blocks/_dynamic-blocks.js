((element, blocks, serverSideRender, blockEditor) =>
{
  window.trader_dynamic_blocks.forEach(dynamic_block =>
  {
    const elem = element.createElement,
      registerBlockType = blocks.registerBlockType,
      ServerSideRender = serverSideRender,
      useBlockProps = blockEditor.useBlockProps;

    registerBlockType(dynamic_block, {
      apiVersion: 2,

      edit: props =>
      {
        return elem(
          'div',
          useBlockProps(),
          elem(ServerSideRender, {
            block: dynamic_block,
            attributes: props.attributes,
          }),
        );
      },
      save: () => null,
    });
  });
})(
  window.wp.element,
  window.wp.blocks,
  window.wp.serverSideRender,
  window.wp.blockEditor,
);