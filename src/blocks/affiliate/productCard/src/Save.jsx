import { useBlockProps } from '@wordpress/block-editor';

import ProductCard from './ProductCard';
import { AttributesType } from './propTypes';

const Save = ({ attributes }) => (
  // eslint-disable-next-line react/jsx-props-no-spreading
  <div {...useBlockProps.save()}>
    {attributes?.productCardSnippet && (
      <ProductCard
        productCardSnippet={attributes.productCardSnippet}
      />
    )}
  </div>
);

Save.propTypes = {
  attributes: AttributesType.isRequired,
};

export default Save;
